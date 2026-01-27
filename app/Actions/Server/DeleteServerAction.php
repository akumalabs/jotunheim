<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Support\Facades\Log;

/**
 * Delete a server from Proxmox
 */
class DeleteServerAction
{
    public function __construct(
        private ProxmoxApiClient $proxmoxClient,
    ) {}

    public function execute(Server $server): string
    {
        Log::info("Deleting server {$server->id} (VMID: {$server->vmid})");

        try {
            $taskUpid = $this->proxmoxClient->deleteVM($server->vmid);

            Log::info("Server {$server->id} deletion initiated", ['task_upid' => $taskUpid]);

            return $taskUpid;
        } catch (ProxmoxApiException $e) {
            Log::error("Failed to delete server {$server->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
