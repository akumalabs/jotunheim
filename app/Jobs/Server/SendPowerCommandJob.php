<?php

namespace App\Jobs\Server;

use App\Enums\Activity\ServerActivity;
use App\Enums\Server\PowerCommand;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Services\ActivityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\Middleware\WithoutOverlapping; // Disabled to prevent stalls
use Illuminate\Queue\SerializesModels;

class SendPowerCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Server $server,
        protected PowerCommand $command
    ) {}

    // public function middleware(): array
    // {
    //     return [(new WithoutOverlapping($this->server->id))->dontRelease()];
    // }

    public function handle(): void
    {
        $client = new \App\Services\Proxmox\ProxmoxApiClient($this->server->node);
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($this->server);

        match ($this->command) {
            PowerCommand::START => $powerRepo->start(),
            PowerCommand::STOP => $powerRepo->stop(),
            PowerCommand::SHUTDOWN => $powerRepo->shutdown(),
            PowerCommand::REBOOT => $powerRepo->reboot(),
            PowerCommand::KILL => $powerRepo->kill(),
            PowerCommand::RESET => $powerRepo->reset(),
        };

        // Log activity
        $activity = match ($this->command) {
            PowerCommand::START => ServerActivity::START,
            PowerCommand::STOP => ServerActivity::STOP,
            PowerCommand::SHUTDOWN => ServerActivity::SHUTDOWN,
            PowerCommand::REBOOT => ServerActivity::RESTART,
            PowerCommand::KILL => ServerActivity::KILL,
            PowerCommand::RESET => ServerActivity::RESTART,
        };

        ActivityService::forServer($this->server, $activity->value);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Power command {$this->command->value} failed for server {$this->server->id}: {$exception->getMessage()}");
    }
}
