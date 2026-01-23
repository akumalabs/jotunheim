<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Models\Template;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * BuildServerJob - Clone template to create VM
 */
class BuildServerJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        protected Server $server,
        protected Template $template,
    ) {}

    public function handle(): void
    {
        logger()->info("Building server {$this->server->vmid} from template {$this->template->vmid}");

        $client = new ProxmoxApiClient($this->server->node);

        // Clone the template
        $upid = $client->cloneVM(
            $this->template->vmid,
            $this->server->vmid,
            $this->server->name,
            $this->server->node->vm_storage
        );

        logger()->info("Clone task started: {$upid}");

        // Store UPID for monitoring
        $this->server->update(['installation_task' => $upid]);
    }
}
