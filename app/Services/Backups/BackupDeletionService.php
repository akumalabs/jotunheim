<?php

namespace App\Services\Backups;

use App\Enums\Activity\ServerActivity;
use App\Jobs\Backup\WaitUntilBackupIsDeletedJob;
use App\Models\Backup;
use App\Repositories\Proxmox\Server\ProxmoxBackupRepository;
use App\Services\ActivityService;

class BackupDeletionService
{
    public function __construct(
        protected ProxmoxBackupRepository $backupRepository
    ) {}

    /**
     * Delete a backup.
     */
    public function handle(Backup $backup): void
    {
        $server = $backup->server;

        // Mark as deleting
        $backup->update(['status' => 'deleting']);

        // Delete from Proxmox
        $this->backupRepository->delete($server, $backup->volume_id);

        // Dispatch job to wait for deletion
        WaitUntilBackupIsDeletedJob::dispatch($backup);

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_DELETE->value, [
            'backup_id' => $backup->id,
            'backup_name' => $backup->name,
        ]);
    }

    /**
     * Force delete (skip Proxmox, just remove record).
     */
    public function forceDelete(Backup $backup): void
    {
        $server = $backup->server;

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_DELETE->value, [
            'backup_id' => $backup->id,
            'backup_name' => $backup->name,
            'force' => true,
        ]);

        $backup->delete();
    }
}
