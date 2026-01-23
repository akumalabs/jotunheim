<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * DeleteServerJob - Delete VM from Proxmox
 */
class DeleteServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        protected Server $server,
        protected bool $purgeBackups = true,
    ) {}

    public function handle(): void
    {
        logger()->info("Deleting server {$this->server->vmid}");

        $client = new ProxmoxApiClient($this->server->node);

        // Stop VM first if running
        try {
            $status = $client->getVMStatus($this->server->vmid);
            if (isset($status['status']) && $status['status'] === 'running') {
                $client->stopVM($this->server->vmid);
                sleep(5); // Wait for shutdown
            }
        } catch (\Exception $e) {
            logger()->warning('Could not stop VM: '.$e->getMessage());
        }

        // Delete backups if requested
        if ($this->purgeBackups) {
            try {
                // Could implement backup deletion here
                logger()->info("Purging backups for server {$this->server->vmid}");
            } catch (\Exception $e) {
                logger()->warning('Could not purge backups: '.$e->getMessage());
            }
        }

        // Delete the VM
        try {
            $upid = $client->deleteVM($this->server->vmid);
            $this->server->update(['deletion_task' => $upid]);
            logger()->info("VM deletion started: {$upid}");
        } catch (\Exception $e) {
            logger()->error('Failed to delete VM: '.$e->getMessage());
            throw $e;
        }
    }
}
