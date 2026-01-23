<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdatePasswordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Server $server,
        protected string $password
    ) {}

    public function handle(): void
    {
        $cloudinitRepo = new ProxmoxCloudinitRepository($this->server);

        // Update cloud-init password
        $cloudinitRepo->configure($this->server, [
            'cipassword' => $this->password,
        ]);

        // Update server record (encrypted)
        $this->server->update([
            'password' => encrypt($this->password),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Password update failed for server {$this->server->id}: {$exception->getMessage()}");
    }
}
