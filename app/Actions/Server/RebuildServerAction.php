<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Rebuild a server on Proxmox
 * Encapsulates server rebuild operations
 */
class RebuildServerAction
{
    public function __construct(
        private ProxmoxApiClient $proxmoxClient,
    ) {}

    public function execute(Server $server): string
    {
        Log::info("Rebuilding server {$server->id} (VMID: {$server->vmid})");

        try {
            $taskUpid = $this->proxmoxClient->deleteVM($server->vmid);

            Log::info("Server {$server->id} rebuild initiated", ['task_upid' => $taskUpid]);

            return $taskUpid;
        } catch (\Exception $e) {
            Log::error("Failed to rebuild server {$server->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
