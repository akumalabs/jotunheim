<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Models\Template;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sets password, updates cloud-init, and starts the VM
 */
class ReconfigureServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        protected Server $server,
        protected Template $template,
        protected string $password,
    ) {}

    public function handle(): void
    {
        logger()->info("Reconfiguring server {$this->server->vmid}");

        $client = new ProxmoxApiClient($this->server->node);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);

        // Set new password via cloud-init
        $configRepo->setPassword($this->password);

        // Regenerate cloud-init image
        $configRepo->update(['cicustom' => '']);

        // Update server status
        $this->server->update([
            'status' => 'installed',
            'installed_at' => now(),
        ]);

        // Start the VM
        try {
            $powerRepo->start();
        } catch (\Exception $e) {
            logger()->warning('Failed to start VM after reinstall: '.$e->getMessage());
        }

        logger()->info("Server {$this->server->vmid} reinstall complete");
    }
}
