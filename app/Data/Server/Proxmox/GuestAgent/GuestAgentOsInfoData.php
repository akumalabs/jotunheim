<?php

namespace App\Data\Server\Proxmox\GuestAgent;

use Spatie\LaravelData\Data;

class GuestAgentOsInfoData extends Data
{
    public function __construct(
        public ?string $name,
        public ?string $version,
        public ?string $kernel,
        public ?string $machine,
        public ?string $id,
        public ?string $prettyName,
    ) {}

    public static function fromProxmox(array $data): self
    {
        $result = $data['result'] ?? $data;

        return new self(
            name: $result['name'] ?? null,
            version: $result['version'] ?? null,
            kernel: $result['kernel-release'] ?? $result['kernel'] ?? null,
            machine: $result['machine'] ?? null,
            id: $result['id'] ?? null,
            prettyName: $result['pretty-name'] ?? null,
        );
    }

    public function isLinux(): bool
    {
        $linuxIds = ['debian', 'ubuntu', 'centos', 'rhel', 'fedora', 'arch', 'alpine'];

        return in_array(strtolower($this->id ?? ''), $linuxIds);
    }

    public function isWindows(): bool
    {
        return str_contains(strtolower($this->name ?? ''), 'windows');
    }
}
