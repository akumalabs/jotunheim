<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * Handles VM backup operations
 */
class ProxmoxBackupRepository extends ProxmoxRepository
{
    /**
     * Create a backup of the VM
     *
     * @param  string  $storage  Storage location for backup
     * @param  string  $mode  Backup mode: snapshot, suspend, stop
     * @param  string  $compress  Compression: 0, gzip, lzo, zstd
     */
    public function create(
        string $storage,
        string $mode = 'snapshot',
        string $compress = 'zstd'
    ): string {
        $response = $this->client->post($this->nodePath('vzdump'), [
            'vmid' => $this->server->vmid,
            'storage' => $storage,
            'mode' => $mode,
            'compress' => $compress,
        ]);

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * List backups for the VM from a storage
     */
    public function list(string $storage): array
    {
        $response = $this->client->get($this->nodePath("storage/{$storage}/content"), [
            'content' => 'backup',
            'vmid' => $this->server->vmid,
        ]);

        return is_array($response) ? $response : [];
    }

    /**
     * Restore from a backup
     *
     * @param  string  $volid  Volume ID of the backup (e.g., 'local:backup/vzdump-qemu-100-2024...')
     * @param  int|null  $newVmid  New VMID for restore (null = use original)
     */
    public function restore(string $volid, ?int $newVmid = null): string
    {
        $params = [
            'archive' => $volid,
            'vmid' => $newVmid ?? $this->server->vmid,
            'force' => 1,
        ];

        $response = $this->client->post($this->nodePath('qemu'), $params);

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * Delete a backup
     */
    public function delete(string $storage, string $volid): string
    {
        $response = $this->client->delete($this->nodePath("storage/{$storage}/content/{$volid}"));

        return is_string($response) ? $response : ($response['data'] ?? '');
    }
}
