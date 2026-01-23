<?php

namespace App\Data\Server\Proxmox\Config;

use Spatie\LaravelData\Data;

class ServerConfigData extends Data
{
    public function __construct(
        public string $name,
        public int $memory,
        public int $cores,
        public int $sockets,
        public string $cpu,
        public ?string $bios,
        public ?string $ostype,
        public ?string $machine,
        public ?bool $agent,
        public ?string $bootdisk,
        public ?string $boot,
        public ?array $disks,
        public ?array $networks,
        public ?array $cloudinit,
    ) {}

    public static function fromProxmox(array $config): self
    {
        return new self(
            name: $config['name'] ?? 'Unknown',
            memory: $config['memory'] ?? 0,
            cores: $config['cores'] ?? 1,
            sockets: $config['sockets'] ?? 1,
            cpu: $config['cpu'] ?? 'host',
            bios: $config['bios'] ?? 'seabios',
            ostype: $config['ostype'] ?? null,
            machine: $config['machine'] ?? null,
            agent: isset($config['agent']) ? (bool) $config['agent'] : null,
            bootdisk: $config['bootdisk'] ?? null,
            boot: $config['boot'] ?? null,
            disks: self::extractDisks($config),
            networks: self::extractNetworks($config),
            cloudinit: self::extractCloudinit($config),
        );
    }

    protected static function extractDisks(array $config): array
    {
        $disks = [];
        $diskPrefixes = ['scsi', 'sata', 'ide', 'virtio'];

        foreach ($diskPrefixes as $prefix) {
            for ($i = 0; $i < 32; $i++) {
                $key = "{$prefix}{$i}";
                if (isset($config[$key])) {
                    $disks[$key] = $config[$key];
                }
            }
        }

        return $disks;
    }

    protected static function extractNetworks(array $config): array
    {
        $networks = [];

        for ($i = 0; $i < 32; $i++) {
            $key = "net{$i}";
            if (isset($config[$key])) {
                $networks[$key] = $config[$key];
            }
        }

        return $networks;
    }

    protected static function extractCloudinit(array $config): ?array
    {
        $cloudinit = [];
        $keys = ['ciuser', 'cipassword', 'sshkeys', 'ipconfig0', 'ipconfig1', 'nameserver', 'searchdomain'];

        foreach ($keys as $key) {
            if (isset($config[$key])) {
                $cloudinit[$key] = $config[$key];
            }
        }

        return ! empty($cloudinit) ? $cloudinit : null;
    }
}
