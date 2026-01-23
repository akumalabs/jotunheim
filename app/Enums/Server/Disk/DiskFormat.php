<?php

namespace App\Enums\Server\Disk;

enum DiskFormat: string
{
    case RAW = 'raw';
    case QCOW2 = 'qcow2';
    case VMDK = 'vmdk';

    public function displayName(): string
    {
        return match ($this) {
            self::RAW => 'Raw disk image',
            self::QCOW2 => 'QCOW2 (Qemu Copy on Write)',
            self::VMDK => 'VMDK (VMware)',
        };
    }

    public function supportsSnapshots(): bool
    {
        return $this === self::QCOW2;
    }

    public function supportsThinProvisioning(): bool
    {
        return $this === self::QCOW2;
    }
}
