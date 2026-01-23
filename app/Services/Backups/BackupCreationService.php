<?php

namespace App\Services\Backups;

use App\Enums\Activity\ServerActivity;
use App\Jobs\Backup\MonitorBackupJob;
use App\Models\Backup;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxBackupRepository;
use App\Services\ActivityService;

class BackupCreationService
{
    public function __construct(
        protected ProxmoxBackupRepository $backupRepository
    ) {}

    /**
     * Create a new backup for the server.
     */
    public function handle(Server $server, string $mode = 'snapshot', string $compression = 'zstd'): Backup
    {
        // Check backup limits
        $this->validateBackupLimit($server);

        // Create backup record
        $backup = Backup::create([
            'server_id' => $server->id,
            'name' => $this->generateBackupName($server),
            'status' => 'creating',
            'mode' => $mode,
            'compression' => $compression,
        ]);

        // Start backup on Proxmox
        $taskId = $this->backupRepository->create($server, [
            'mode' => $mode,
            'compress' => $compression,
            'storage' => $server->node->backup_storage ?? 'local',
        ]);

        $backup->update(['task_id' => $taskId]);

        // Dispatch job to monitor backup progress
        MonitorBackupJob::dispatch($backup);

        // Log activity
        ActivityService::forServer($server, ServerActivity::BACKUP_CREATE->value, [
            'backup_id' => $backup->id,
            'mode' => $mode,
        ]);

        return $backup;
    }

    /**
     * Validate backup limit hasn't been exceeded.
     */
    protected function validateBackupLimit(Server $server): void
    {
        $limit = $server->backup_limit ?? config('midgard.backups.limit', 10);
        $current = $server->backups()->where('status', '!=', 'failed')->count();

        if ($current >= $limit) {
            throw new \Exception("Backup limit of {$limit} has been reached.");
        }
    }

    /**
     * Generate a unique backup name.
     */
    protected function generateBackupName(Server $server): string
    {
        return sprintf(
            'vzdump-qemu-%d-%s',
            $server->vmid,
            now()->format('Y_m_d-H_i_s')
        );
    }
}
