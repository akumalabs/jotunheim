<?php

namespace App\Repositories\Proxmox\Node;

use App\Data\Node\NodeStatusData;
use App\Data\Node\StorageData;
use App\Models\Node;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * Handles node-level operations
 */
class ProxmoxNodeRepository
{
    protected ProxmoxApiClient $client;

    protected Node $node;

    public function __construct(ProxmoxApiClient $client)
    {
        $this->client = $client;
    }

    public function setNode(Node $node): static
    {
        $this->node = $node;
        $this->client->setNode($node);

        return $this;
    }

    protected function nodePath(string $endpoint = ''): string
    {
        $base = "/nodes/{$this->node->cluster}";

        return $endpoint ? "{$base}/{$endpoint}" : $base;
    }

    /**
     * Get node status
     */
    public function getStatus(): NodeStatusData
    {
        $response = $this->client->get($this->nodePath('status'));
        $data = is_array($response) ? $response : [];

        return NodeStatusData::fromProxmox($data);
    }

    /**
     * List all storages on the node
     *
     * @return StorageData[]
     */
    public function getStorages(): array
    {
        $response = $this->client->get($this->nodePath('storage'));

        if (! is_array($response)) {
            return [];
        }

        return array_map(
            fn ($item) => StorageData::fromProxmox($item),
            $response
        );
    }

    /**
     * Get a specific storage
     */
    public function getStorage(string $name): ?StorageData
    {
        $storages = $this->getStorages();

        foreach ($storages as $storage) {
            if ($storage->storage === $name) {
                return $storage;
            }
        }

        return null;
    }

    /**
     * Get next available VMID
     */
    public function getNextVmid(): int
    {
        $response = $this->client->get('/cluster/nextid');

        return (int) $response;
    }

    /**
     * List all VMs on the node
     */
    public function listVms(): array
    {
        $response = $this->client->get($this->nodePath('qemu'));

        return is_array($response) ? $response : [];
    }

    /**
     * List all templates on the node
     */
    public function listTemplates(): array
    {
        $vms = $this->listVms();

        return array_filter($vms, fn ($vm) => ($vm['template'] ?? 0) == 1);
    }

    /**
     * Get ISOs available on a storage
     */
    public function getIsos(string $storage): array
    {
        $response = $this->client->get($this->nodePath("storage/{$storage}/content"), [
            'content' => 'iso',
        ]);

        return is_array($response) ? $response : [];
    }

    /**
     * Download ISO from URL
     */
    public function downloadIso(string $storage, string $url, string $filename): string
    {
        $response = $this->client->post($this->nodePath("storage/{$storage}/download-url"), [
            'content' => 'iso',
            'filename' => $filename,
            'url' => $url,
        ]);

        return is_string($response) ? $response : ($response['data'] ?? '');
    }

    /**
     * Delete an ISO
     */
    public function deleteIso(string $storage, string $volid): string
    {
        $response = $this->client->delete($this->nodePath("storage/{$storage}/content/{$volid}"));

        return is_string($response) ? $response : ($response['data'] ?? '');
    }
}
