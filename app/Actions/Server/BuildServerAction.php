<?php

namespace App\Actions\Server;

use App\Models\Deployment;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Support\Facades\Log;

/**
 * Build a server on Proxmox
 * Encapsulates server creation logic
 */
class BuildServerAction
{
    public function __construct(
        private ProxmoxConfigRepository $configRepository,
        private ProxmoxPowerRepository $powerRepository,
        private ProxmoxServerRepository $serverRepository,
    ) {}

    /**
     * Execute server build
     */
    public function execute(Server $server): string
    {
        Log::info("Building server {$server->id} (VMID: {$server->vmid})");

        try {
            $this->configureResources($server);
            $taskUpid = $this->configureDisk($server);
            
            $this->powerRepository->setServer($server)->start();

            Log::info("Server {$server->id} build initiated", ['task_upid' => $taskUpid]);

            return $taskUpid;
        } catch (ProxmoxApiException $e) {
            Log::error("Failed to build server {$server->id}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function configureResources(Server $server): void
    {
        $this->configRepository->setServer($server)->update([
            'cores' => $server->cpu,
            'memory' => (int) ($server->memory / 1024 / 1024),
        ]);
    }

    protected function configureDisk(Server $server): string
    {
        $taskUpid = $this->configRepository->setServer($server)->resizeDisk('scsi0', $server->disk);
        
        return is_string($taskUpid) ? $taskUpid : ($taskUpid['data'] ?? '');
    }
}
