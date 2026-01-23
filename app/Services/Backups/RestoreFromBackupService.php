<?php

namespace App\Services\Backups;

use App\Enums\Activity\ServerActivity;
use App\Jobs\Backup\MonitorBackupRestorationJob;
use App\Models\Backup;
use App\Repositories\Proxmox\Server\ProxmoxBackupRepository;
use App\Services\ActivityService;

class RestoreFromBackupService
{
    public function __construct(
        protected ProxmoxBackupRepository $backupRepository
    ) {}

    /**
     * Restore a server from backup.
     */
    public function handle(Backup $backup): void
    {
        $server = $backup->server;

        // Update backup status
        $backup->update(['status' => 'restoring']);

        // Restore from Proxmox
        $taskId = $this->backupRepository->restore($server, $backup->volume_id);

        $backup->update(['task_id' => $taskId]);

        // Dispatch job to monitor restoration
        MonitorBackupRestorationJob::dispatch($backup);

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_RESTORE->value, [
            'backup_id' => $backup->id,
            'backup_name' => $backup->name,
        ]);
    }
}
