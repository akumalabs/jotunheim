<?php

namespace App\Jobs\Backup;

use App\Models\Backup;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public int $backoff = 10;

    public function __construct(
        protected Backup $backup
    ) {}

    public function handle(): void
    {
        $server = $this->backup->server;
        $activityRepo = new ProxmoxActivityRepository($server);

        // Check task status
        $task = $activityRepo->getTaskStatus($this->backup->task_id);

        if ($task['status'] === 'running') {
            // Still running, retry
            $this->release(10);

            return;
        }

        if ($task['status'] === 'stopped' && ($task['exitstatus'] ?? '') === 'OK') {
            // Backup completed successfully
            $this->backup->update([
                'status' => 'completed',
                'size' => $task['size'] ?? null,
                'completed_at' => now(),
            ]);
        } else {
            // Backup failed
            $this->backup->update([
                'status' => 'failed',
                'error' => $task['exitstatus'] ?? 'Unknown error',
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->backup->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
