<?php

namespace App\Enums\Server;

enum BackupMode: string
{
    case SNAPSHOT = 'snapshot';
    case SUSPEND = 'suspend';
    case STOP = 'stop';

    public function description(): string
    {
        return match ($this) {
            self::SNAPSHOT => 'Snapshot mode (fastest, VM stays running)',
            self::SUSPEND => 'Suspend mode (VM paused during backup)',
            self::STOP => 'Stop mode (VM stopped during backup, most consistent)',
        };
    }
}
