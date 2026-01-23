<?php

namespace App\Enums\Server;

/**
 * Represents the lifecycle status of a server in the panel
 */
enum ServerStatus: string
{
    case INSTALLING = 'installing';
    case INSTALLED = 'installed';
    case INSTALL_FAILED = 'install_failed';
    case SUSPENDED = 'suspended';
    case DELETING = 'deleting';

    /**
     * Human readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::INSTALLING => 'Installing',
            self::INSTALLED => 'Installed',
            self::INSTALL_FAILED => 'Install Failed',
            self::SUSPENDED => 'Suspended',
            self::DELETING => 'Deleting',
        };
    }

    /**
     * CSS badge class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::INSTALLING => 'info',
            self::INSTALLED => 'success',
            self::INSTALL_FAILED => 'danger',
            self::SUSPENDED => 'warning',
            self::DELETING => 'secondary',
        };
    }

    /**
     * Can user control power?
     */
    public function canControlPower(): bool
    {
        return match ($this) {
            self::INSTALLED => true,
            default => false,
        };
    }

    /**
     * Is terminal operation?
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::INSTALLED, self::INSTALL_FAILED, self::SUSPENDED => true,
            default => false,
        };
    }
}
