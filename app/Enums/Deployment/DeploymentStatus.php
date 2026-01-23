<?php

namespace App\Enums\Deployment;

enum DeploymentStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED]);
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::RUNNING => 'blue',
            self::COMPLETED => 'green',
            self::FAILED => 'red',
            self::CANCELLED => 'gray',
        };
    }

    /**
     * Get icon for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::RUNNING => 'spinner',
            self::COMPLETED => 'check',
            self::FAILED => 'x-circle',
            self::CANCELLED => 'ban',
        };
    }
}
