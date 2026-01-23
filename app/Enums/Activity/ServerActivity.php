<?php

namespace App\Enums\Activity;

enum ServerActivity: string
{
    // Power events
    case START = 'server:power.start';
    case STOP = 'server:power.stop';
    case RESTART = 'server:power.restart';
    case SHUTDOWN = 'server:power.shutdown';
    case KILL = 'server:power.kill';

    // Configuration events
    case UPDATE_CONFIG = 'server:config.update';
    case CHANGE_PASSWORD = 'server:config.password';
    case MOUNT_ISO = 'server:config.mount_iso';
    case UNMOUNT_ISO = 'server:config.unmount_iso';

    // Lifecycle events
    case CREATE = 'server:lifecycle.create';
    case DELETE = 'server:lifecycle.delete';
    case REINSTALL = 'server:lifecycle.reinstall';
    case SUSPEND = 'server:lifecycle.suspend';
    case UNSUSPEND = 'server:lifecycle.unsuspend';

    // Backup events
    case BACKUP_CREATE = 'server:backup.create';
    case BACKUP_RESTORE = 'server:backup.restore';
    case BACKUP_DELETE = 'server:backup.delete';

    // Snapshot events
    case SNAPSHOT_CREATE = 'server:snapshot.create';
    case SNAPSHOT_ROLLBACK = 'server:snapshot.rollback';
    case SNAPSHOT_DELETE = 'server:snapshot.delete';

    // Console events
    case CONSOLE_ACCESS = 'server:console.access';

    /**
     * Get a human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::START => 'Started server',
            self::STOP => 'Stopped server',
            self::RESTART => 'Restarted server',
            self::SHUTDOWN => 'Shutdown server',
            self::KILL => 'Force killed server',
            self::UPDATE_CONFIG => 'Updated server configuration',
            self::CHANGE_PASSWORD => 'Changed server password',
            self::MOUNT_ISO => 'Mounted ISO image',
            self::UNMOUNT_ISO => 'Unmounted ISO image',
            self::CREATE => 'Created server',
            self::DELETE => 'Deleted server',
            self::REINSTALL => 'Reinstalled server',
            self::SUSPEND => 'Suspended server',
            self::UNSUSPEND => 'Unsuspended server',
            self::BACKUP_CREATE => 'Created backup',
            self::BACKUP_RESTORE => 'Restored from backup',
            self::BACKUP_DELETE => 'Deleted backup',
            self::SNAPSHOT_CREATE => 'Created snapshot',
            self::SNAPSHOT_ROLLBACK => 'Rolled back to snapshot',
            self::SNAPSHOT_DELETE => 'Deleted snapshot',
            self::CONSOLE_ACCESS => 'Accessed console',
        };
    }
}
