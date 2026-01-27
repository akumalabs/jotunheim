<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * Handles VM configuration operations
 */
class ProxmoxConfigRepository extends ProxmoxRepository
{
    /**
     * Get the full VM configuration
     */
    public function get(): array
    {
        $response = $this->client->get($this->vmPath('config'));

        return is_array($response) ? $response : [];
    }

    /**
     * Update VM configuration
     */
    public function update(array $params): string
    {
        $response = $this->client->post($this->vmPath('config'), $params);

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * Update CPU configuration
     */
    public function setCpu(int $cores, int $sockets = 1): string
    {
        return $this->update([
            'cores' => $cores,
            'sockets' => $sockets,
        ]);
    }

    /**
     * Update memory configuration (in bytes)
     */
    public function setMemory(int $bytes): string
    {
        $mb = (int) floor($bytes / 1048576);

        return $this->update([
            'memory' => $mb,
        ]);
    }

    /**
     * Resize disk - returns immediately without waiting
     */
    public function resizeDisk(string $disk, int $bytes): string
    {
        $kib = (int) round($bytes / 1024);

        $response = $this->client->resizeDisk(
            $this->requireServer()->vmid,
            $disk,
            $bytes
        );

        return $response['data'] ?? '';
    }

    /**
     * Set cloud-init password
     */
    public function setPassword(string $password): string
    {
        return $this->update([
            'cipassword' => $password,
        ]);
    }

    /**
     * Set cloud-init SSH keys
     */
    public function setSshKeys(string $keys): string
    {
        return $this->update([
            'sshkeys' => rawurlencode($keys),
        ]);
    }

    /**
     * Set boot order
     *
     * @param  array  $devices  Device order (e.g., ['scsi0', 'ide2', 'net0'])
     */
    public function setBootOrder(array $devices): string
    {
        return $this->update([
            'boot' => 'order='.implode(';', $devices),
        ]);
    }

    /**
     * Mount ISO to VM
     */
    public function mountIso(string $storage, string $iso, string $device = 'ide2'): string
    {
        return $this->update([
            $device => "{$storage}:iso/{$iso},media=cdrom",
        ]);
    }

    /**
     * Unmount ISO from VM
     */
    public function unmountIso(string $device = 'ide2'): string
    {
        return $this->update([
            $device => 'none,media=cdrom',
        ]);
    }
}
