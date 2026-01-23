<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxStatisticsRepository - VM RRD statistics
 */
class ProxmoxStatisticsRepository extends ProxmoxRepository
{
    /**
     * Get RRD data for a VM.
     */
    public function getRrd(string $timeframe = 'hour', string $cf = 'AVERAGE'): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/rrddata",
            ['timeframe' => $timeframe, 'cf' => $cf]
        );
    }

    /**
     * Get hourly statistics.
     */
    public function getHourly(): array
    {
        return $this->getRrd('hour');
    }

    /**
     * Get daily statistics.
     */
    public function getDaily(): array
    {
        return $this->getRrd('day');
    }

    /**
     * Get weekly statistics.
     */
    public function getWeekly(): array
    {
        return $this->getRrd('week');
    }

    /**
     * Get monthly statistics.
     */
    public function getMonthly(): array
    {
        return $this->getRrd('month');
    }

    /**
     * Get yearly statistics.
     */
    public function getYearly(): array
    {
        return $this->getRrd('year');
    }

    /**
     * Get current status (not historical).
     */
    public function getCurrent(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/status/current"
        );
    }

    /**
     * Get network statistics from current status.
     */
    public function getNetworkStats(): array
    {
        $current = $this->getCurrent();

        return [
            'netin' => $current['netin'] ?? 0,
            'netout' => $current['netout'] ?? 0,
        ];
    }

    /**
     * Get disk IO statistics from current status.
     */
    public function getDiskStats(): array
    {
        $current = $this->getCurrent();

        return [
            'diskread' => $current['diskread'] ?? 0,
            'diskwrite' => $current['diskwrite'] ?? 0,
        ];
    }

    /**
     * Get CPU usage percentage.
     */
    public function getCpuUsage(): float
    {
        $current = $this->getCurrent();

        return ($current['cpu'] ?? 0) * 100;
    }

    /**
     * Get memory usage.
     */
    public function getMemoryUsage(): array
    {
        $current = $this->getCurrent();

        return [
            'used' => $current['mem'] ?? 0,
            'total' => $current['maxmem'] ?? 0,
            'percent' => ($current['maxmem'] ?? 1) > 0
                ? (($current['mem'] ?? 0) / ($current['maxmem'] ?? 1)) * 100
                : 0,
        ];
    }
}
