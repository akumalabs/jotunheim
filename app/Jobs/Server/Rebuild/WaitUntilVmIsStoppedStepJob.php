<?php

namespace App\Jobs\Server\Rebuild;

use App\Enums\Rebuild\RebuildStep;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaitUntilVmIsStoppedStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;
    public int $backoff = 5;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        if ($this->attempts() <= 1) {
            Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::STOPPING_SERVER->value, 1200);
            Log::info("[Rebuild] Server {$this->server->id}: Stopping server [{$this->server->vmid}]");
        }

        $client = new ProxmoxApiClient($this->server->node);

        try {
            for ($i = 0; $i < 30; $i++) {
                $status = $client->getVMStatus($this->server->vmid);
                if ($status['status'] === 'stopped') {
                    Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} stopped successfully");
                    return;
                }
                sleep(2);
            }

            $status = $client->getVMStatus($this->server->vmid);
            if ($status['status'] === 'running') {
                Log::info("[Rebuild] Server {$this->server->id}: VM still running, releasing...");
                $this->release($this->backoff);
                return;
            }
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Error stopping - " . $e->getMessage());
            $this->release($this->backoff);
            return;
        }

        Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} is stopped");
    }
}
