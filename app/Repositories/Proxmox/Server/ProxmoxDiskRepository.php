<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxDiskRepository - VM disk operations
 */
class ProxmoxDiskRepository extends ProxmoxRepository
{
    /**
     * Resize a disk.
     *
     * @param  string  $disk  e.g., 'scsi0', 'virtio0'
     * @param  string  $size  e.g., '+10G' to add 10GB
     */
    public function resize(string $disk, string $size): array|string
    {
        return $this->client->put(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/resize",
            ['disk' => $disk, 'size' => $size]
        );
    }

    /**
     * Move disk to different storage.
     */
    public function move(string $disk, string $targetStorage, ?string $format = null): array|string
    {
        $params = [
            'disk' => $disk,
            'storage' => $targetStorage,
        ];

        if ($format) {
            $params['format'] = $format;
        }

        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/move_disk",
            $params
        );
    }

    /**
     * Get disk info from VM config.
     */
    public function getDisks(): array
    {
        $config = $this->client->get("/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config");

        $disks = [];
        $diskPatterns = ['scsi', 'virtio', 'ide', 'sata'];

        foreach ($config as $key => $value) {
            foreach ($diskPatterns as $pattern) {
                if (preg_match("/^{$pattern}\d+$/", $key)) {
                    $disks[$key] = $this->parseDiskValue($value);
                }
            }
        }

        return $disks;
    }

    /**
     * Parse disk value string.
     */
    protected function parseDiskValue(string $value): array
    {
        $parts = explode(',', $value);
        $disk = ['raw' => $value];

        // First part is storage:volume
        if (isset($parts[0])) {
            $disk['volume'] = $parts[0];
            if (str_contains($parts[0], ':')) {
                [$disk['storage'], $disk['image']] = explode(':', $parts[0], 2);
            }
        }

        // Parse size
        foreach ($parts as $part) {
            if (str_starts_with($part, 'size=')) {
                $disk['size'] = substr($part, 5);
            }
        }

        return $disk;
    }

    /**
     * Add a new disk.
     */
    public function add(string $disk, string $storage, string $size): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            [$disk => "{$storage}:{$size}"]
        );
    }

    /**
     * Remove a disk (detach and optionally delete).
     */
    public function remove(string $disk, bool $deleteData = false): array|string
    {
        $params = ['delete' => $disk];

        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            $params
        );
    }
}
