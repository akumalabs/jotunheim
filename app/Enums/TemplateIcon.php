<?php

namespace App\Enums;

enum TemplateIcon: string
{
    case LINUX = 'linux';
    case UBUNTU = 'ubuntu';
    case DEBIAN = 'debian';
    case CENTOS = 'centos';
    case FEDORA = 'fedora';
    case ARCH = 'arch';
    case ALPINE = 'alpine';
    case RHEL = 'rhel';
    case ROCKY = 'rocky';
    case ALMA = 'alma';
    case WINDOWS = 'windows';
    case FREEBSD = 'freebsd';
    case OPENBSD = 'openbsd';
    case OTHER = 'other';

    /**
     * Get display name.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::LINUX => 'Linux',
            self::UBUNTU => 'Ubuntu',
            self::DEBIAN => 'Debian',
            self::CENTOS => 'CentOS',
            self::FEDORA => 'Fedora',
            self::ARCH => 'Arch Linux',
            self::ALPINE => 'Alpine Linux',
            self::RHEL => 'Red Hat',
            self::ROCKY => 'Rocky Linux',
            self::ALMA => 'AlmaLinux',
            self::WINDOWS => 'Windows',
            self::FREEBSD => 'FreeBSD',
            self::OPENBSD => 'OpenBSD',
            self::OTHER => 'Other',
        };
    }

    /**
     * Detect icon from template name.
     */
    public static function fromName(string $name): self
    {
        $lower = strtolower($name);

        return match (true) {
            str_contains($lower, 'ubuntu') => self::UBUNTU,
            str_contains($lower, 'debian') => self::DEBIAN,
            str_contains($lower, 'centos') => self::CENTOS,
            str_contains($lower, 'fedora') => self::FEDORA,
            str_contains($lower, 'arch') => self::ARCH,
            str_contains($lower, 'alpine') => self::ALPINE,
            str_contains($lower, 'rhel') || str_contains($lower, 'red hat') => self::RHEL,
            str_contains($lower, 'rocky') => self::ROCKY,
            str_contains($lower, 'alma') => self::ALMA,
            str_contains($lower, 'windows') => self::WINDOWS,
            str_contains($lower, 'freebsd') => self::FREEBSD,
            str_contains($lower, 'openbsd') => self::OPENBSD,
            str_contains($lower, 'linux') => self::LINUX,
            default => self::OTHER,
        };
    }
}
