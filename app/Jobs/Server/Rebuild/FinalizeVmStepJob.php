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

class FinalizeVmStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 600;
    public int $backoff = 10;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        if ($this->attempts() <= 1) {
            Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::FINALIZING->value, 1200);
            Log::info("[Rebuild] Server {$this->server->id}: Finalizing VM {$this->server->vmid} (guest agent check)");
        }

        $client = new ProxmoxApiClient($this->server->node);

        try {
            $client->post("/nodes/{$this->server->node->cluster}/qemu/{$this->server->vmid}/agent/ping", []);

            Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} guest agent reachable. Finalizing complete.");

            $this->server->update([
                'status' => 'running',
                'is_installing' => false,
                'installation_task' => null,
                'installed_at' => now(),
            ]);

            Cache::forget("server_rebuild_step_{$this->server->id}");

        } catch (\Exception $e) {
             Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} guest agent not ready yet - " . $e->getMessage());
             $this->release($this->backoff);
        }
    }
}
