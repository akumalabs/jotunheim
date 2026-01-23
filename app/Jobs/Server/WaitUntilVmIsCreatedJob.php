<?php

namespace App\Jobs\Server;

use App\Enums\Rebuild\RebuildStep;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaitUntilVmIsCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;
    public int $backoff = 5;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        if (! $this->server->installation_task) {
            Log::warning("[Rebuild] Server {$this->server->id}: No installation task for VM {$this->server->vmid}");
            return;
        }

        $client = new ProxmoxApiClient($this->server->node);
        $activityRepo = (new ProxmoxActivityRepository($client))->setNode($this->server->node);

        try {
            for ($i = 0; $i < 60; $i++) {
                $status = $activityRepo->getTaskStatus($this->server->installation_task);

                if ($status['status'] === 'stopped') {
                    if (($status['exitstatus'] ?? 'OK') !== 'OK') {
                        Log::error("[Rebuild] Server {$this->server->id}: Clone failed - " . ($status['exitstatus'] ?? 'Unknown error'));
                        $this->server->update(['status' => 'install_failed']);
                        return;
                    }

                    Log::info("[Rebuild] Server {$this->server->id}: Clone completed successfully");
                    Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::CONFIGURING_RESOURCES->value, 1200);
                    return;
                }

                sleep(2);
            }

            $this->release($this->backoff);

        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Failed to check task status - " . $e->getMessage());
            $this->release($this->backoff);
        }
    }
}
