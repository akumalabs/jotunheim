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
 * WaitUntilVmIsDeletedJob - Poll until VM is fully deleted
 */
class WaitUntilVmIsDeletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 30;

    public int $backoff = 5;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        $client = new ProxmoxApiClient($this->server->node);

        try {
            // Try to get VM status - if it exists, still deleting
            $client->getVMStatus($this->server->vmid);

            // VM still exists, retry later
            $this->release($this->backoff);

        } catch (\Exception $e) {
            // VM doesn't exist anymore - deletion complete
            logger()->info("Server {$this->server->vmid} deleted successfully");

            // Delete the database record
            $this->server->delete();
        }
    }
}
