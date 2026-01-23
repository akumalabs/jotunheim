<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 30;

    public int $backoff = 5;

    public function __construct(
        protected Server $server,
        protected string $expectedState,
        protected ?string $taskId = null
    ) {}

    public function handle(): void
    {
        $activityRepo = new ProxmoxActivityRepository($this->server);

        // If we have a task ID, wait for it to complete
        if ($this->taskId) {
            $task = $activityRepo->getTaskStatus($this->taskId);

            if ($task['status'] === 'running') {
                $this->release(5);

                return;
            }
        }

        // Check current state
        $status = $activityRepo->getCurrentStatus();
        $currentState = $status['status'] ?? 'unknown';

        if ($currentState === $this->expectedState) {
            // State reached - update server status
            $this->server->update(['status' => $currentState]);

            return;
        }

        // Not yet in expected state, retry
        $this->release(5);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Monitor state job failed for server {$this->server->id}: {$exception->getMessage()}");
    }
}
