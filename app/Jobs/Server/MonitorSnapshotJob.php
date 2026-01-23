<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public int $backoff = 10;

    public function __construct(
        protected Server $server,
        protected string $taskId,
        protected string $operation = 'create' // create, rollback, delete
    ) {}

    public function handle(): void
    {
        $activityRepo = new ProxmoxActivityRepository($this->server);

        $task = $activityRepo->getTaskStatus($this->taskId);

        if ($task['status'] === 'running') {
            $this->release(10);

            return;
        }

        if ($task['status'] === 'stopped' && ($task['exitstatus'] ?? '') === 'OK') {
            \Log::info("Snapshot {$this->operation} completed for server {$this->server->id}");
        } else {
            \Log::error("Snapshot {$this->operation} failed for server {$this->server->id}: ".($task['exitstatus'] ?? 'Unknown'));
        }
    }
}
