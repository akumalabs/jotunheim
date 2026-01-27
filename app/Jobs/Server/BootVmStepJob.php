<?php

namespace App\Jobs\Server\Rebuild;

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

class BootVmStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    public function __construct(
        protected Server $server,
    ) {}

    public function handle(): void
    {
        if ($this->attempts() <= 1) {
            Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::BOOTING_SERVER->value, 1200);
            Log::info("[Rebuild] Server {$this->server->id}: Booting VM {$this->server->vmid}");
        }

        $client = new ProxmoxApiClient($this->server->node);
        $activityRepo = (new ProxmoxActivityRepository($client))->setNode($this->server->node);

        try {
            $upid = $client->startVM((int) $this->server->vmid);
            Log::info("[Rebuild] Server {$this->server->id}: Boot started with UPID {$upid}");

            $activityRepo->waitForTask($upid, 180);

            Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} booted successfully");

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already running')) {
                 Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} already running");
                 return;
            }
            Log::error("[Rebuild] Server {$this->server->id}: Boot failed - " . $e->getMessage());
            throw $e;
        }
    }
}
