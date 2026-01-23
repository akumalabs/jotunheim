<?php

namespace App\Data\Server;

use Spatie\LaravelData\Data;

/**
 * Represents allocated resources for a server
 */
class ServerResourceData extends Data
{
    public function __construct(
        public int $cpu,
        public int $memory,           // bytes
        public int $disk,             // bytes
        public ?int $bandwidthLimit,  // bytes, null = unlimited
        public int $bandwidthUsage,   // bytes used this period
        public int $snapshotLimit,
        public int $backupLimit,
    ) {}

    /**
     * Create from Server model
     */
    public static function fromModel($server): self
    {
        return new self(
            cpu: $server->cpu,
            memory: $server->memory,
            disk: $server->disk,
            bandwidthLimit: $server->bandwidth_limit,
            bandwidthUsage: $server->bandwidth_usage ?? 0,
            snapshotLimit: $server->snapshot_limit ?? 3,
            backupLimit: $server->backup_limit ?? 2,
        );
    }

    /**
     * Format memory for display
     */
    public function memoryFormatted(): string
    {
        return $this->formatBytes($this->memory);
    }

    /**
     * Format disk for display
     */
    public function diskFormatted(): string
    {
        return $this->formatBytes($this->disk);
    }

    /**
     * Format bandwidth limit for display
     */
    public function bandwidthLimitFormatted(): string
    {
        if ($this->bandwidthLimit === null) {
            return 'Unlimited';
        }

        return $this->formatBytes($this->bandwidthLimit);
    }

    /**
     * Get bandwidth usage percentage
     */
    public function bandwidthUsagePercent(): float
    {
        if ($this->bandwidthLimit === null || $this->bandwidthLimit === 0) {
            return 0;
        }

        return round(($this->bandwidthUsage / $this->bandwidthLimit) * 100, 1);
    }

    /**
     * Check if over bandwidth limit
     */
    public function isOverBandwidthLimit(): bool
    {
        if ($this->bandwidthLimit === null) {
            return false;
        }

        return $this->bandwidthUsage >= $this->bandwidthLimit;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2).' '.$units[$i];
    }
}
