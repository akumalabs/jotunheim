<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WaitUntilVmIsStopped implements ShouldQueue
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
        $repository = (new ProxmoxServerRepository($client))->setServer($this->server);

        $state = $repository->getState();

        if ($state->state->value !== 'stopped') {
            $this->release($this->backoff);

            return;
        }

        logger()->info("Server {$this->server->vmid} is now stopped");
    }
}
