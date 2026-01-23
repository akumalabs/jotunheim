<?php

namespace App\Services\Servers;

use App\Jobs\Server\DeleteServerJob;
use App\Jobs\Server\WaitUntilVmIsDeletedJob;
use App\Models\Server;
use Illuminate\Support\Facades\Bus;

/**
 * ServerDeletionService - Orchestrates server deletion
 */
class ServerDeletionService
{
    /**
     * Delete a server (marks for deletion and dispatches job).
     */
    public function delete(Server $server, bool $purgeBackups = true): void
    {
        // Mark as deleting
        $server->update(['status' => 'deleting']);

        // Release all addresses
        $server->addresses()->update([
            'server_id' => null,
            'is_primary' => false,
        ]);

        // Dispatch deletion job chain
        Bus::chain([
            new DeleteServerJob($server, $purgeBackups),
            new WaitUntilVmIsDeletedJob($server),
        ])->dispatch();
    }

    /**
     * Force delete a server immediately without job chain.
     */
    public function forceDelete(Server $server): void
    {
        try {
            $client = new \App\Services\Proxmox\ProxmoxApiClient($server->node);

            // Try to stop first
            try {
                $client->stopVM($server->vmid);
                sleep(5);
            } catch (\Exception $e) {
                // Ignore if already stopped
            }

            // Delete VM
            $client->deleteVM($server->vmid);

        } catch (\Exception $e) {
            logger()->error('Failed to delete VM from Proxmox: '.$e->getMessage());
        }

        // Release addresses
        $server->addresses()->update([
            'server_id' => null,
            'is_primary' => false,
        ]);

        // Delete server record
        $server->delete();
    }
}
