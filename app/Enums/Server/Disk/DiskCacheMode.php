<?php

namespace App\Enums\Server\Disk;

enum DiskCacheMode: string
{
    case NONE = 'none';
    case WRITEBACK = 'writeback';
    case WRITETHROUGH = 'writethrough';
    case DIRECTSYNC = 'directsync';
    case UNSAFE = 'unsafe';

    public function displayName(): string
    {
        return match ($this) {
            self::NONE => 'No cache',
            self::WRITEBACK => 'Write back',
            self::WRITETHROUGH => 'Write through',
            self::DIRECTSYNC => 'Direct sync',
            self::UNSAFE => 'Unsafe (fastest)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NONE => 'Disable caching entirely',
            self::WRITEBACK => 'Good balance of performance and safety',
            self::WRITETHROUGH => 'Writes go to storage immediately',
            self::DIRECTSYNC => 'O_DIRECT + fsync',
            self::UNSAFE => 'Maximum performance, data loss risk',
        };
    }
}
