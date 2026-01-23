<?php

namespace App\Data\Server;

use App\Enums\Server\State;
use Spatie\LaravelData\Data;

/**
 * Represents real-time VM status from Proxmox
 */
class ServerStateData extends Data
{
    public function __construct(
        public State $state,
        public float $cpuUsed,
        public int $memoryTotal,
        public int $memoryUsed,
        public int $diskTotal,
        public int $diskUsed,
        public int $uptime,
        public int $netIn,
        public int $netOut,
    ) {}

    /**
     * Create from Proxmox API response
     */
    public static function fromProxmox(array $data): self
    {
        return new self(
            state: State::fromProxmox($data['status'] ?? 'unknown'),
            cpuUsed: (float) ($data['cpu'] ?? 0),
            memoryTotal: (int) ($data['maxmem'] ?? 0),
            memoryUsed: (int) ($data['mem'] ?? 0),
            diskTotal: (int) ($data['maxdisk'] ?? 0),
            diskUsed: (int) ($data['disk'] ?? 0),
            uptime: (int) ($data['uptime'] ?? 0),
            netIn: (int) ($data['netin'] ?? 0),
            netOut: (int) ($data['netout'] ?? 0),
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
     * Get disk usage as percentage
     */
    public function diskPercent(): float
    {
        if ($this->diskTotal === 0) {
            return 0;
        }

        return round(($this->diskUsed / $this->diskTotal) * 100, 1);
    }

    /**
     * Format uptime as human readable string
     */
    public function uptimeFormatted(): string
    {
        if ($this->uptime === 0) {
            return '—';
        }

        $days = floor($this->uptime / 86400);
        $hours = floor(($this->uptime % 86400) / 3600);
        $minutes = floor(($this->uptime % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        }
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}
