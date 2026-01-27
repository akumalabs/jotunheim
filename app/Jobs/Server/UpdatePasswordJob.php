<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use Illuminate\Support\Facades\Log;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdatePasswordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 300;

    public function __construct(
        protected Server $server,
        protected string $password
    ) {}

    public function handle(): void
    {
        $cloudinitRepo = new ProxmoxCloudinitRepository($this->server);
        $serverRepo = new \App\Repositories\Proxmox\Server\ProxmoxServerRepository(
            new \App\Services\Proxmox\ProxmoxApiClient($this->server->node)
        )->setServer($this->server);

        // Update cloud-init password with retry logic
        $attempts = 0;
        $maxAttempts = 5;
        $backoffs = [5, 10, 15, 20, 30];

        while ($attempts < $maxAttempts) {
            try {
                if (!$serverRepo->waitUntilUnlocked(30, 2)) {
                    throw new \Exception("VM locked timeout before password update");
                }

                $cloudinitRepo->setPassword($this->password);
                break;
            } catch (ProxmoxApiException $e) {
                $attempts++;
                $msg = $e->getMessage();

                if (str_contains($msg, 'lock') || str_contains($msg, 'timeout')) {
                    if ($attempts >= $maxAttempts) {
                        throw new \Exception("Password update failed after {$maxAttempts} attempts: " . $msg, 0, $e);
                    }
                    $sleep = $backoffs[$attempts - 1] ?? 30;
                    \Log::warning("Password update failed (Lock/Timeout), retry {$attempts}/{$maxAttempts} in {$sleep}s...");
                    sleep($sleep);
                } else {
                    throw $e;
                }
            }
        }

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
