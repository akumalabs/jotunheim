<?php

namespace App\Jobs\Server\Rebuild;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleRebuildFailureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        protected Server $server,
        protected string $previousStatus
    ) {}

    public function handle(): void
    {
        // This is a no-op job - it should never run in the normal success path
        // The actual completion is handled by FinalizeVmStepJob
        // IMPORTANT: This job only runs when the chain catches an exception
        // Normal rebuild completion is handled by FinalizeVmStepJob, not this job
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Rebuild failed for server {$this->server->id}: {$exception->getMessage()}");

        $this->server->update([
            'status' => $this->previousStatus === 'running' ? 'running' : 'failed',
            'is_installing' => false,
            'installation_task' => null,
        ]);

        Cache::forget("server_rebuild_step_{$this->server->id}");
    }
}
