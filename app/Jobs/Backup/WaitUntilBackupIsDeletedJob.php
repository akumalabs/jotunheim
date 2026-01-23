<?php

namespace App\Jobs\Backup;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WaitUntilBackupIsDeletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 30;

    public int $backoff = 5;

    public function __construct(
        protected Backup $backup
    ) {}

    public function handle(): void
    {
        // For now, just delete the record after marking as deleting
        // In a production system, you'd verify it's actually deleted from Proxmox storage
        $this->backup->delete();
    }

    public function failed(\Throwable $exception): void
    {
        // Mark as failed if we couldn't complete deletion
        $this->backup->update([
            'status' => 'failed',
            'error' => 'Deletion failed: '.$exception->getMessage(),
        ]);
    }
}
