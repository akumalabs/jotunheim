<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Models\Template;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

/**
 * This job dispatches a chain of jobs to safely reinstall a server
 */
class ReinstallServerJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        protected Server $server,
        protected Template $template,
        protected string $password,
    ) {}

    public function handle(): void
    {
        logger()->info("Starting reinstall for server {$this->server->vmid}");

        // Update server status
        $this->server->update(['status' => 'reinstalling']);

        $client = new ProxmoxApiClient($this->server->node);

        // 1. Stop the VM if running
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);

        try {
            $powerRepo->kill();
        } catch (\Exception $e) {
            // VM might already be stopped
            logger()->info('VM might already be stopped: '.$e->getMessage());
        }

        // 2. Wait for VM to stop, then proceed with disk operations
        Bus::chain([
            new WaitUntilVmIsStopped($this->server),
            new WaitUntilVmIsUnlocked($this->server),
            new ReconfigureServerJob($this->server, $this->template, $this->password),
        ])->dispatch();
    }
}
