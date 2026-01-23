<?php

namespace App\Jobs\Backup;

use App\Models\Backup;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MonitorBackupRestorationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 120;

    public int $backoff = 15;

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
            $this->release(15);

            return;
        }

        if ($task['status'] === 'stopped' && ($task['exitstatus'] ?? '') === 'OK') {
            // Restoration completed successfully
            $this->backup->update([
                'status' => 'completed',
            ]);
        } else {
            // Restoration failed
            $this->backup->update([
                'status' => 'failed',
                'error' => 'Restoration failed: '.($task['exitstatus'] ?? 'Unknown error'),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->backup->update([
            'status' => 'failed',
            'error' => 'Restoration failed: '.$exception->getMessage(),
        ]);
    }
}
