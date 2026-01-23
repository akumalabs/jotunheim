<?php

namespace App\Enums\Server;

enum PowerCommand: string
{
    case START = 'start';
    case STOP = 'stop';
    case SHUTDOWN = 'shutdown';
    case REBOOT = 'reboot';
    case KILL = 'kill';

    /**
     * Get the Proxmox API endpoint for this command
     */
    public function getProxmoxEndpoint(): string
    {
        return match ($this) {
            self::START => 'start',
            self::STOP => 'stop',
            self::SHUTDOWN => 'shutdown',
            self::REBOOT => 'reboot',
            self::KILL => 'stop',
        };
    }

    /**
     * Whether this command requires timeout parameter
     */
    public function requiresTimeout(): bool
    {
        return match ($this) {
            self::SHUTDOWN, self::REBOOT => true,
            default => false,
        };
    }
}
