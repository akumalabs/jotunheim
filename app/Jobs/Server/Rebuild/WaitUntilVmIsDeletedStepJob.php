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

class WaitUntilVmIsDeletedStepJob implements ShouldQueue
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
            Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::DELETING_SERVER->value, 1200);
        }

        $client = new ProxmoxApiClient($this->server->node);

        try {
            for ($i = 0; $i < 30; $i++) {
                if (!$client->vmidExists($this->server->vmid)) {
                    Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} confirmed deleted");
                    return;
                }
                sleep(2);
            }

            if ($client->vmidExists($this->server->vmid)) {
                Log::info("[Rebuild] Server {$this->server->id}: VM still exists, releasing...");
                $this->release($this->backoff);
                return;
            }
        } catch (\Exception $e) {
            Log::warning("[Rebuild] Server {$this->server->id}: Error checking VM existence - " . $e->getMessage());
            $this->release($this->backoff);
            return;
        }

        Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} is confirmed deleted");
    }
}
