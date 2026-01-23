<?php

namespace App\Enums\Server;

enum BiosType: string
{
    case SEABIOS = 'seabios';
    case OVMF = 'ovmf';

    public function displayName(): string
    {
        return match ($this) {
            self::SEABIOS => 'SeaBIOS (Legacy)',
            self::OVMF => 'OVMF (UEFI)',
        };
    }

    public function supportsSecureBoot(): bool
    {
        return $this === self::OVMF;
    }
}
