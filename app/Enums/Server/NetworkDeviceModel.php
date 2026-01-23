<?php

namespace App\Enums\Server;

enum NetworkDeviceModel: string
{
    case VIRTIO = 'virtio';
    case E1000 = 'e1000';
    case RTL8139 = 'rtl8139';
    case VMXNET3 = 'vmxnet3';

    public function displayName(): string
    {
        return match ($this) {
            self::VIRTIO => 'VirtIO (paravirtualized)',
            self::E1000 => 'Intel E1000',
            self::RTL8139 => 'Realtek RTL8139',
            self::VMXNET3 => 'VMware vmxnet3',
        };
    }

    public function isParavirtualized(): bool
    {
        return $this === self::VIRTIO;
    }
}
