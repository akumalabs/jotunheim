<?php

namespace App\Data\Node;

use Spatie\LaravelData\Data;

/**
 * Represents a Proxmox storage with capacity info
 */
class StorageData extends Data
{
    public function __construct(
        public string $storage,
        public string $type,
        public string $content,
        public int $total,
        public int $used,
        public int $available,
        public bool $enabled,
        public bool $active,
        public bool $shared,
    ) {}

    /**
     * Create from Proxmox API response
     */
    public static function fromProxmox(array $data): self
    {
        return new self(
            storage: $data['storage'] ?? '',
            type: $data['type'] ?? 'unknown',
            content: $data['content'] ?? '',
            total: (int) ($data['total'] ?? 0),
            used: (int) ($data['used'] ?? 0),
            available: (int) ($data['avail'] ?? 0),
            enabled: (bool) ($data['enabled'] ?? true),
            active: (bool) ($data['active'] ?? true),
            shared: (bool) ($data['shared'] ?? false),
        );
    }

    /**
     * Get usage percentage
     */
    public function usagePercent(): float
    {
        if ($this->total === 0) {
            return 0;
        }

        return round(($this->used / $this->total) * 100, 1);
    }

    /**
     * Check if storage supports content type
     */
    public function supportsContent(string $type): bool
    {
        $types = explode(',', $this->content);

        return in_array($type, $types);
    }

    /**
     * Check if can store VMs
     */
    public function supportsImages(): bool
    {
        return $this->supportsContent('images');
    }

    /**
     * Check if can store ISOs
     */
    public function supportsIso(): bool
    {
        return $this->supportsContent('iso');
    }

    /**
     * Check if can store backups
     */
    public function supportsBackup(): bool
    {
        return $this->supportsContent('backup');
    }
}
