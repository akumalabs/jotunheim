<?php

namespace App\Enums\Server;

enum DiskInterface: string
{
    case SCSI = 'scsi';
    case SATA = 'sata';
    case IDE = 'ide';
    case VIRTIO = 'virtio';

    public function displayName(): string
    {
        return match ($this) {
            self::SCSI => 'SCSI',
            self::SATA => 'SATA',
            self::IDE => 'IDE',
            self::VIRTIO => 'VirtIO',
        };
    }

    public function maxDevices(): int
    {
        return match ($this) {
            self::SCSI => 31,
            self::SATA => 6,
            self::IDE => 4,
            self::VIRTIO => 16,
        };
    }

    public function supportsHotplug(): bool
    {
        return in_array($this, [self::SCSI, self::VIRTIO]);
    }
}
