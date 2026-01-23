<?php

namespace App\Data\Server\Proxmox\Backup;

use Spatie\LaravelData\Data;

class BackupData extends Data
{
    public function __construct(
        public string $volid,
        public string $format,
        public int $size,
        public ?string $ctime,
        public ?string $notes,
        public ?int $vmid,
        public string $storage,
    ) {}

    public static function fromProxmox(array $data): self
    {
        return new self(
            volid: $data['volid'] ?? '',
            format: $data['format'] ?? 'vma',
            size: $data['size'] ?? 0,
            ctime: isset($data['ctime']) ? date('Y-m-d H:i:s', $data['ctime']) : null,
            notes: $data['notes'] ?? null,
            vmid: $data['vmid'] ?? null,
            storage: explode(':', $data['volid'] ?? '')[0] ?? 'local',
        );
    }

    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }
}
