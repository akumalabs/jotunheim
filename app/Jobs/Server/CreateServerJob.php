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

            // Set Cosmetic Name (Must use PUT to allow spaces)
            logger()->info("Setting cosmetic VM name via PUT...");
            $configRepo->updateViaPut(['name' => $this->server->name]);

            // 4. Resize Disk
            logger()->info("Resizing disk...");
            // Defaulting to scsi0, commonly used
            $configRepo->resizeDisk('scsi0', $this->server->disk); // Takes bytes

            // Wait for Unlock after Resize (Fix Race Condition)
            if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                 throw new \Exception("VM locked timeout after resize.");
            }

            // 5. Cloud-Init Configuration
            logger()->info("Applying Cloud-Init...");
            
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
                // If specific keys passed, use them. Otherwise, could fetch from user profile if we had that logic.
                // For now, using passed keys.
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

            $cloudinitRepo->configure($ciConfig);

            // Force regenerate cloud-init image to ensure changes apply
            $cloudinitRepo->regenerate();

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
