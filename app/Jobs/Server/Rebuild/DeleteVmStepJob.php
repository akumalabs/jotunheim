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

class DeleteVmStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::DELETING_SERVER->value, 1200);

        $client = new ProxmoxApiClient($this->server->node);

        try {
            Log::info("[Rebuild] Server {$this->server->id}: Deleting VM {$this->server->vmid}");
            $client->deleteVM($this->server->vmid);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'No such file')) {
                 Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} already deleted");
                 return;
            }
            Log::warning("[Rebuild] Server {$this->server->id}: Delete failed - " . $e->getMessage());
            throw $e;
        }
    }
}
