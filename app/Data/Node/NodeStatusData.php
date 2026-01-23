<?php

namespace App\Data\Node;

use Spatie\LaravelData\Data;

/**
 * Represents real-time node status from Proxmox
 */
class NodeStatusData extends Data
{
    public function __construct(
        public string $status,
        public float $cpuUsed,
        public int $cpuCores,
        public int $memoryTotal,
        public int $memoryUsed,
        public int $memoryFree,
        public int $swapTotal,
        public int $swapUsed,
        public int $uptime,
        public string $kernelVersion,
        public string $pveVersion,
    ) {}

    /**
     * Create from Proxmox API response
     */
    public static function fromProxmox(array $data): self
    {
        return new self(
            status: ($data['uptime'] ?? 0) > 0 ? 'online' : 'offline',
            cpuUsed: (float) ($data['cpu'] ?? 0),
            cpuCores: (int) ($data['cpuinfo']['cpus'] ?? $data['cpus'] ?? 0),
            memoryTotal: (int) ($data['memory']['total'] ?? $data['maxmem'] ?? 0),
            memoryUsed: (int) ($data['memory']['used'] ?? $data['mem'] ?? 0),
            memoryFree: (int) ($data['memory']['free'] ?? 0),
            swapTotal: (int) ($data['swap']['total'] ?? 0),
            swapUsed: (int) ($data['swap']['used'] ?? 0),
            uptime: (int) ($data['uptime'] ?? 0),
            kernelVersion: $data['kversion'] ?? $data['kernel-release'] ?? 'unknown',
            pveVersion: $data['pveversion'] ?? 'unknown',
        );
    }

    /**
     * Get CPU usage as percentage
     */
    public function cpuPercent(): float
    {
        return round($this->cpuUsed * 100, 1);
    }

    /**
     * Get memory usage as percentage
     */
    public function memoryPercent(): float
    {
        if ($this->memoryTotal === 0) {
            return 0;
        }

        return round(($this->memoryUsed / $this->memoryTotal) * 100, 1);
    }

    /**
     * Format uptime as human readable
     */
    public function uptimeFormatted(): string
    {
        if ($this->uptime === 0) {
            return 'Offline';
        }

        $days = floor($this->uptime / 86400);
        $hours = floor(($this->uptime % 86400) / 3600);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        return "{$hours}h";
    }

    /**
     * Check if node is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }
}
