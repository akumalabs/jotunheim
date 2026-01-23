<?php

namespace App\Data\Server\Proxmox\GuestAgent;

use Spatie\LaravelData\Data;

class GuestAgentInfoData extends Data
{
    public function __construct(
        public bool $enabled,
        public ?string $version,
        public ?array $supportedCommands,
    ) {}

    public static function fromProxmox(array $data): self
    {
        return new self(
            enabled: isset($data['result']) || isset($data['version']),
            version: $data['result']['version'] ?? $data['version'] ?? null,
            supportedCommands: $data['result']['supported_commands'] ?? null,
        );
    }

    public function supportsCommand(string $command): bool
    {
        if (! $this->supportedCommands) {
            return false;
        }

        foreach ($this->supportedCommands as $cmd) {
            if (($cmd['name'] ?? '') === $command && ($cmd['enabled'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
