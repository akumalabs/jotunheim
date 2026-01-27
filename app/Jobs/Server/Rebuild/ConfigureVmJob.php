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

    public int $tries = 3;
    public int $timeout = 300;

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
            $serverRepo = (new \App\Repositories\Proxmox\Server\ProxmoxServerRepository($client))->setServer($this->server);
            if (!$serverRepo->waitUntilUnlocked(60, 2)) {
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

            // Resize Disk (Defaulting to scsi0)
            if ($this->server->disk > 0) {
                 // Wait for unlock after potential hardware update above
                 if (!$serverRepo->waitUntilUnlocked(30, 1)) {
                      throw new \Exception("VM locked timeout before disk resize.");
                 }

                 Log::info("[Rebuild] Server {$this->server->id}: Resizing disk to {$this->server->disk} bytes");
                 $configRepo->resizeDisk('scsi0', $this->server->disk);
                 
                 // Wait for Unlock after Resize (Fix Race Condition)
                 $serverRepo = (new \App\Repositories\Proxmox\Server\ProxmoxServerRepository($client))->setServer($this->server);
                 if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                      throw new \Exception("VM locked timeout after resize.");
                 }
            }

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
