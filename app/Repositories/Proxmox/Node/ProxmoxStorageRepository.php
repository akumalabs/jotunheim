<?php

namespace App\Repositories\Proxmox\Node;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxStorageRepository - Detailed storage operations
 */
class ProxmoxStorageRepository extends ProxmoxRepository
{
    /**
     * Get all storages on this node.
     */
    public function getStorages(): array
    {
        return $this->client->get("/nodes/{$this->node->cluster}/storage");
    }

    /**
     * Get storage content (ISOs, images, etc).
     */
    public function getContent(string $storage, ?string $content = null): array
    {
        $endpoint = "/nodes/{$this->node->cluster}/storage/{$storage}/content";
        $params = $content ? ['content' => $content] : [];

        return $this->client->get($endpoint, $params);
    }

    /**
     * Get ISOs available on a storage.
     */
    public function getIsos(string $storage): array
    {
        return $this->getContent($storage, 'iso');
    }

    /**
     * Get VM templates available on a storage.
     */
    public function getVzTemplates(string $storage): array
    {
        return $this->getContent($storage, 'vztmpl');
    }

    /**
     * Get backup files on a storage.
     */
    public function getBackups(string $storage): array
    {
        return $this->getContent($storage, 'backup');
    }

    /**
     * Download ISO from URL.
     */
    public function downloadIso(string $storage, string $url, string $filename): array|string
    {
        return $this->client->post("/nodes/{$this->node->cluster}/storage/{$storage}/download-url", [
            'content' => 'iso',
            'filename' => $filename,
            'url' => $url,
        ]);
    }

    /**
     * Delete a volume from storage.
     */
    public function deleteVolume(string $storage, string $volume): array|string
    {
        return $this->client->delete("/nodes/{$this->node->cluster}/storage/{$storage}/content/{$volume}");
    }

    /**
     * Get storage status.
     */
    public function getStorageStatus(string $storage): array
    {
        return $this->client->get("/nodes/{$this->node->cluster}/storage/{$storage}/status");
    }

    /**
     * Check if storage supports a content type.
     */
    public function supportsContent(array $storage, string $contentType): bool
    {
        $content = $storage['content'] ?? '';

        return str_contains($content, $contentType);
    }

    /**
     * Get storages that support images (for VM disks).
     */
    public function getImageStorages(): array
    {
        $storages = $this->getStorages();

        return array_filter($storages, fn ($s) => $this->supportsContent($s, 'images'));
    }

    /**
     * Get storages that support ISOs.
     */
    public function getIsoStorages(): array
    {
        $storages = $this->getStorages();

        return array_filter($storages, fn ($s) => $this->supportsContent($s, 'iso'));
    }
}
