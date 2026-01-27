<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // No retries - ghost configs from failed clones cause "already exists" errors
    public int $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Server $server,
        protected int $templateVmid,
        protected ?string $password = null,
        protected array $sshKeys = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        logger()->info("Starting creation details for Server: {$this->server->id} (VMID: {$this->server->vmid})");

        $client = new ProxmoxApiClient($this->server->node);
        $serverRepo = (new ProxmoxServerRepository($client))->setServer($this->server);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($this->server);
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);

        try {
            // 1. Clone VM (Safe Name)
            logger()->info("Cloning template {$this->templateVmid} to VMID {$this->server->vmid}...");
            
            $taskUpid = $client->cloneVM(
                $this->templateVmid,
                (int) $this->server->vmid,
                [
                    'name' => $this->server->hostname ?? \Illuminate\Support\Str::slug($this->server->name),
                ]
            );

            // Wait for clone
            if (is_string($taskUpid) && str_contains($taskUpid, 'UPID:')) {
                $client->waitForTask($taskUpid, 300); // 5 mins max clone
            }
            
            logger()->info("Clone complete. Waiting for unlock...");

            // 2. Wait for Unlock
            if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                 throw new \Exception("VM locked timeout after clone.");
            }

            // 3. Configure Resources (CPU/RAM + Cosmetic Name)
            logger()->info("Configuring resources...");
            $configRepo->update([
                'cores' => $this->server->cpu,
                'memory' => (int) ($this->server->memory / 1024 / 1024), // Bytes to MB
                'description' => "Managed by Midgard Panel | User: {$this->server->user_id}",
                'onboot' => 1,
            ]);

            // Wait for lock to clear after resource update
            if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                 throw new \Exception("VM locked timeout after resource config.");
            }

            // 4. Resize Disk
            logger()->info("Resizing disk...");
            
            // Smart Resize: Check if already correct size
            $shouldResize = true;
            try {
                 $currentConfig = $configRepo->get();
                 $diskString = $currentConfig['scsi0'] ?? $currentConfig['virtio0'] ?? $currentConfig['ide0'] ?? $currentConfig['sata0'] ?? null;
                 
                 if ($diskString && preg_match('/size=(\d+(\.\d+)?[TGMK]?)/', $diskString, $matches)) {
                     $sizeStr = $matches[1];
                     $unit = substr($sizeStr, -1);
                     $value = (float) substr($sizeStr, 0, -1);
                     if (is_numeric($unit)) {
                         $value = (float) $sizeStr; 
                         $unit = 'B'; 
                     }
                     
                     $bytes = match(strtoupper($unit)) {
                         'T' => $value * 1024 * 1024 * 1024 * 1024,
                         'G' => $value * 1024 * 1024 * 1024,
                         'M' => $value * 1024 * 1024,
                         'K' => $value * 1024,
                         default => $value,
                     };
                     
                     if ($bytes >= ($this->server->disk - 1048576)) {
                         logger()->info("Disk already at requested size ({$sizeStr}). Skipping resize.");
                         $shouldResize = false;
                     }
                 }
            } catch (\Exception $e) {
                 logger()->warning("Failed to parse disk size: " . $e->getMessage());
            }

            if ($shouldResize) {
                // Defaulting to scsi0, commonly used
                try {
                    $configRepo->resizeDisk('scsi0', $this->server->disk); 
                    
                    // Wait for Unlock after Resize (Fix Race Condition)
                    if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                         throw new \Exception("VM locked timeout after resize.");
                    }
                } catch (\Exception $e) {
                    // Ignore "size match" errors if they slip through
                    if (str_contains($e->getMessage(), 'smaller than') || str_contains($e->getMessage(), 'size match')) {
                        logger()->info("Resize skipped by PVE (size match).");
                    } else {
                        throw $e;
                    }
                }
            }
            
            // 60 attempts * 2s = 120s => Increased to 300 attempts * 2s = 600s
            // This catches the lock from Resource Config (if resize skipped) or Resize (double check)
            if (!$serverRepo->waitUntilUnlocked(300, 2)) {
                 throw new \Exception("VM locked timeout before Cloud-Init.");
            }

            // 5. Cloud-Init Configuration
            logger()->info("Applying Cloud-Init...");
            
            // ... (setup ciConfig code unchanged) ...
            // Detect OS from template name for appropriate default user  
            $template = \App\Models\Template::where('vmid', $this->templateVmid)->first();
            $isWindows = $template && (
                stripos($template->name, 'windows') !== false || 
                stripos($template->name, 'win') !== false
            );
            
            $ciConfig = [
                'user' => $isWindows ? 'Administrator' : 'root',
            ];

            if ($this->password) {
                $ciConfig['password'] = $this->password;
                logger()->info("Password configured for Cloud-Init.");
            } else {
                logger()->warning("No password provided for Cloud-Init.");
            }

            // SSH Keys (User specific keys + provided keys)
            if (!empty($this->sshKeys) || $this->server->user) {
                 $ciConfig['ssh_keys'] = $this->sshKeys;
            }
            
            // Network Configuration
            $address = $this->server->primaryAddress();
            if ($address) {
                logger()->info("Configuring network: {$address->full_address}");
                $ciConfig['ip'] = $address->full_address;
                $ciConfig['gateway'] = $address->gateway;
            } else {
                 logger()->warning("No primary address assigned. IP configuration skipped.");
            }

            // Retry Loop for Cloud-Init Configuration
            // This step is critical and often hits lock contention.
            $ciAttempts = 0;
            $maxCiAttempts = 10;
            $ciBackoff = [5, 10, 15, 20, 30, 40, 50, 60, 60, 60];

            while ($ciAttempts < $maxCiAttempts) {
                try {
                     $cloudinitRepo->configure($ciConfig);
                     break; // Success
                } catch (ProxmoxApiException $e) {
                     $ciAttempts++;
                     $msg = $e->getMessage();
                     
                     if (str_contains($msg, 'lock') || str_contains($msg, 'timeout')) {
                         if ($ciAttempts >= $maxCiAttempts) {
                             throw new \Exception("Cloud-Init config failed after {$maxCiAttempts} attempts: " . $msg, 0, $e);
                         }
                         $sleep = $ciBackoff[$ciAttempts - 1] ?? 60;
                         logger()->warning("Cloud-Init config failed (Lock/Timeout), retry {$ciAttempts}/{$maxCiAttempts} in {$sleep}s...");
                         sleep($sleep);
                         
                         // Re-check unlock before retry
                         $serverRepo->waitUntilUnlocked(30, 2);
                     } else {
                         throw $e; // Fatal error if not lock related
                     }
                }
            }

            // Force regenerate cloud-init image to ensure changes apply (with retry)
            $regenAttempts = 0;
            $maxRegenAttempts = 5;
            
            while ($regenAttempts < $maxRegenAttempts) {
                try {
                    $cloudinitRepo->regenerate();
                    break;
                } catch (ProxmoxApiException $e) {
                    $regenAttempts++;
                    $msg = $e->getMessage();
                    
                    if (str_contains($msg, 'lock') || str_contains($msg, 'timeout')) {
                        if ($regenAttempts >= $maxRegenAttempts) {
                            logger()->warning("Cloud-Init regenerate failed after {$maxRegenAttempts} attempts, proceeding anyway");
                            break;
                        }
                        $sleep = 10;
                        logger()->warning("Cloud-Init regenerate failed (Lock/Timeout), retry {$regenAttempts}/{$maxRegenAttempts} in {$sleep}s...");
                        sleep($sleep);
                        $serverRepo->waitUntilUnlocked(30, 2);
                    } else {
                        throw $e;
                    }
                }
            }

            // 6. Start VM
            logger()->info("Starting VM...");
            $powerRepo->start();

            // 7. Update DB Status
            $this->server->update([
                'status' => 'running',
                'is_installing' => false,
                'installed_at' => now(),
            ]);

            logger()->info("Server {$this->server->id} creation successful.");

        } catch (\Exception $e) {
            logger()->error("Server creation failed: " . $e->getMessage());
            
            $this->server->update([
                'status' => 'failed',
                'is_installing' => false,
            ]);

            throw $e;
        }
    }
}
