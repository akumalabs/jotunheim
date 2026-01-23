<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * Handles VM snapshot operations
 */
class ProxmoxSnapshotRepository extends ProxmoxRepository
{
    /**
     * List all snapshots for the VM
     */
    public function list(): array
    {
        $response = $this->client->get($this->vmPath('snapshot'));

        return is_array($response) ? $response : [];
    }

    /**
     * Create a new snapshot
     *
     * @param  string  $name  Snapshot name
     * @param  string|null  $description  Optional description
     * @param  bool  $includeRam  Include RAM in snapshot
     */
    public function create(string $name, ?string $description = null, bool $includeRam = false): string
    {
        $params = [
            'snapname' => $name,
            'vmstate' => $includeRam ? 1 : 0,
        ];

        if ($description) {
            $params['description'] = $description;
        }

        $response = $this->client->post($this->vmPath('snapshot'), $params);

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * Get snapshot configuration
     */
    public function get(string $name): array
    {
        $response = $this->client->get($this->vmPath("snapshot/{$name}/config"));

        return is_array($response) ? $response : [];
    }

    /**
     * Rollback to a snapshot
     */
    public function rollback(string $name): string
    {
        $response = $this->client->post($this->vmPath("snapshot/{$name}/rollback"));

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * Delete a snapshot
     */
    public function delete(string $name): string
    {
        $response = $this->client->delete($this->vmPath("snapshot/{$name}"));

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * Update snapshot description
     */
    public function updateDescription(string $name, string $description): void
    {
        $this->client->put($this->vmPath("snapshot/{$name}/config"), [
            'description' => $description,
        ]);
    }
}
