<?php

namespace App\Enums\Server;

enum State: string
{
    case RUNNING = 'running';
    case STOPPED = 'stopped';
    case PAUSED = 'paused';
    case SUSPENDED = 'suspended';
    case UNKNOWN = 'unknown';

    /**
     * Create from Proxmox status string
     */
    public static function fromProxmox(string $status): self
    {
        return match (strtolower($status)) {
            'running' => self::RUNNING,
            'stopped' => self::STOPPED,
            'paused' => self::PAUSED,
            'suspended' => self::SUSPENDED,
            default => self::UNKNOWN,
        };
    }

    /**
     * Human readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'Running',
            self::STOPPED => 'Stopped',
            self::PAUSED => 'Paused',
            self::SUSPENDED => 'Suspended',
            self::UNKNOWN => 'Unknown',
        };
    }

    /**
     * CSS class for status badge
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::RUNNING => 'success',
            self::STOPPED => 'danger',
            self::PAUSED => 'warning',
            self::SUSPENDED => 'warning',
            self::UNKNOWN => 'secondary',
        };
    }
}
