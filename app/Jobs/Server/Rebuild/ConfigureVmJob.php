<?php

namespace App\Jobs\Server\Rebuild;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConfigureVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 600;

    public function __construct(
        protected Server $server,
        protected ?string $password = null,
        protected array $addressIds = [],
        protected array $sshKeyIds = [],
        protected ?Deployment $deployment = null,
        protected ?int $stepId = null,
    ) {}

    public function handle(): void
    {
        $step = null;
        if ($this->stepId) {
            $step = DeploymentStep::find($this->stepId);
            $step?->start();
        }
        Cache::put("server_rebuild_step_{$this->server->id}", \App\Enums\Rebuild\RebuildStep::CONFIGURING_RESOURCES->value, 1200);
        
        Log::info("[Rebuild] Server {$this->server->id}: Configuring VM {$this->server->vmid}");
        
        try {
            $client = new ProxmoxApiClient($this->server->node);
            
            // Wait for VM to unlock (Clone might still be holding lock)
            // Increased to 600s (300 attempts * 2s) as requested
            $serverRepo = (new \App\Repositories\Proxmox\Server\ProxmoxServerRepository($client))->setServer($this->server);
            if (!$serverRepo->waitUntilUnlocked(300, 2)) {
                 throw new \Exception("VM locked timeout before configuration start.");
            }

            $cloudInitRepo = (new \App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository($client))
                ->setServer($this->server);
            $configRepo = (new \App\Repositories\Proxmox\Server\ProxmoxConfigRepository($client))
                ->setServer($this->server);

            // 0. Configure Hardware Resources (CPU/RAM/Disk)
            Log::info("[Rebuild] Server {$this->server->id}: Applying hardware resources...");
            $configRepo->update([
                'cores' => $this->server->cpu,
                'memory' => (int) ($this->server->memory / 1024 / 1024), // Bytes to MB
                'onboot' => 1,
            ]);
            if ($this->server->disk > 0) {
                 // 1. Pre-check: Don't resize if already large enough
                 try {
                     $currentConfig = $configRepo->get();
                     // Check scsi0, virtio0, ide0, sata0
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
                         
                         // Allow small variance (1MB) due to rounding
                         if ($bytes >= ($this->server->disk - 1048576)) {
                             Log::info("[Rebuild] Server {$this->server->id}: Disk already at requested size ({$sizeStr}). Skipping resize.");
                             goto after_resize;
                         }
                     }
                 } catch (\Exception $e) {
                     Log::warning("[Rebuild] Failed to parse disk size: " . $e->getMessage());
                 }

                 // Wait for transient file locks to clear after hardware update
                 sleep(10);
                 
                 Log::info("[Rebuild] Server {$this->server->id}: Resizing disk to {$this->server->disk} bytes");
                 
                 // Retry loop for resize with exponential backoff (10s, 20s, 40s, 60s, 60s)
                 $attempts = 0;
                 $maxResizeAttempts = 5;
                 $backoffs = [10, 20, 40, 60, 60];
                 
                 while ($attempts < $maxResizeAttempts) {
                     try {
                         // Check unlock before each attempt
                         if (!$serverRepo->waitUntilUnlocked(300, 2)) {
                             throw new \Exception("VM locked timeout before resize attempt {$attempts}");
                         }
                         
                         $configRepo->resizeDisk('scsi0', $this->server->disk);
                         break; // Success
                     } catch (\Exception $e) {
                         $attempts++;
                         $msg = $e->getMessage();
                         
                         // If "disk already at that size", success
                         if (str_contains($msg, 'smaller than') || str_contains($msg, 'size match')) {
                             break;
                         }
                         
                         if ($attempts >= $maxResizeAttempts) {
                             if (str_contains($msg, 'timeout') || str_contains($msg, 'locked')) {
                                 throw $e;
                             }
                             Log::warning("[Rebuild] Resize failed but proceeding: " . $msg);
                         } else {
                             $sleepTime = $backoffs[$attempts - 1] ?? 60;
                             Log::warning("[Rebuild] Resize attempt {$attempts} failed (Lock/Timeout), retrying in {$sleepTime}s...");
                             sleep($sleepTime);
                         }
                     }
                 }
                 
                 // Post-resize unlock wait: increased to 300s
                 if (!$serverRepo->waitUntilUnlocked(150, 2)) {
                      throw new \Exception("VM locked timeout after resize.");
                 }
            }
            
            after_resize:

            // 1. Configure User/Password
            $config = [];
            if ($this->password) {
                $config['password'] = $this->password;
            }
            // Default user 'root' or 'ubuntu' etc usually handled by template default or we can set strict defaults if needed
            // For now, let's assume we only set password if provided.

            // 2. Configure SSH Keys
            if (!empty($this->sshKeyIds)) {
                $keys = \App\Models\SshKey::whereIn('id', $this->sshKeyIds)->pluck('public_key')->toArray();
                if (!empty($keys)) {
                     $config['ssh_keys'] = $keys;
                }
            }

            // 3. Configure Network
            if (!empty($this->addressIds)) {
                // Determine primary address
                $addresses = \App\Models\Address::whereIn('id', $this->addressIds)->get();
                $primary = $addresses->where('is_primary', true)->first() ?? $addresses->first();
                
                if ($primary) {
                    $config['ip'] = $primary->address . '/' . $primary->cidr;
                    $config['gateway'] = $primary->gateway;
                }
            } else {
                 // Fallback to server's existing network config if no IDs passed (or maybe just primary)
                 $primary = $this->server->addresses()->where('is_primary', true)->first();
                 if ($primary) {
                     $config['ip'] = $primary->address . '/' . $primary->cidr;
                     $config['gateway'] = $primary->gateway;
                 }
            }
            
            // Apply Configuration
            if (!empty($config)) {
                $cloudInitRepo->configure($config);
            }

            Log::info("[Rebuild] Server {$this->server->id}: VM configured successfully");
            
            if ($step) {
                $step->complete();
            }
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Failed to configure VM - " . $e->getMessage());
            
            if ($step) {
                $step->fail($e->getMessage(), 'configure_failed');
            }
            
            throw $e;
        }
    }
}
