<?php

namespace App\Services;

use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Support\Facades\Log;

class BandwidthTrackingService
{
    protected ProxmoxApiClient $client;

    public function __construct(ProxmoxApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Track bandwidth usage for all servers on a node.
     */
    public function trackForNode(string $nodeName): void
    {
        try {
            $response = $this->client->get('/nodes/'.$nodeName.'/status');

            if (! isset($response['network'])) {
                Log::warning("No network data for node {$nodeName}");

                return;
            }

            $network = $response['network'];
            $in = $this->convertToBytes($network['in'] ?? 0);
            $out = $this->convertToBytes($network['out'] ?? 0);

            Log::info("Node {$nodeName} bandwidth: in={$in}, out={$out}");

            Server::whereHas('node', function ($query) use ($nodeName) {
                $query->where('cluster', $nodeName)
                    ->orWhere('name', $nodeName);
            })->get()->each(function ($server) use ($in, $out) {
                $server->update([
                    'bandwidth_usage' => $this->calculateBandwidth($server->bandwidth_limit, $server->bandwidth_usage, $in, $out),
                ]);

                $this->sendAlertIfThreshold($server, $in, $out);
            });

        } catch (ProxmoxApiException $e) {
            Log::error("Failed to track bandwidth for node {$nodeName}: ".$e->getMessage());
        }
    }

    /**
     * Calculate total bandwidth usage.
     */
    protected function calculateBandwidth(int $limit, int $current, int $in, int $out): int
    {
        $newUsage = max($in, $out);

        if ($limit === 0 || $limit === -1) {
            return $newUsage;
        }

        return min($current + $newUsage, $limit);
    }

    /**
     * Convert Proxmox network bytes to bytes.
     */
    protected function convertToBytes(float $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return (int) $value;
    }

    /**
     * Send alerts based on bandwidth thresholds.
     */
    protected function sendAlertIfThreshold(Server $server, int $in, int $out): void
    {
        $limit = $server->bandwidth_limit;

        if ($limit === 0 || $limit === -1) {
            return;
        }

        $percentage = ($server->bandwidth_usage / $limit) * 100;

        if ($percentage >= 100 && ! $this->hasLoggedAlert($server, 'overage')) {
            Log::warning("Server {$server->uuid} bandwidth overage detected", [
                'usage' => $server->bandwidth_usage,
                'limit' => $limit,
            ]);

            Log::channel('alerts')->error("Bandwidth Overage Alert: Server {$server->name} ({$server->uuid}) has exceeded its bandwidth limit");
        }

        if ($percentage >= 90 && ! $this->hasLoggedAlert($server, '90_percent')) {
            Log::channel('alerts')->warning("Bandwidth Alert: Server {$server->name} ({$server->uuid}) at 90% bandwidth limit");
        }

        if ($percentage >= 80 && ! $this->hasLoggedAlert($server, '80_percent')) {
            Log::channel('alerts')->warning("Bandwidth Alert: Server {$server->name} ({$server->uuid}) at 80% bandwidth limit");
        }
    }

    /**
     * Check if alert was already logged (avoid duplicate alerts).
     */
    protected function hasLoggedAlert(Server $server, string $type): bool
    {
        $lastAlert = Log::channel('alerts')
            ->where('message', 'like', "%{$server->uuid}%")
            ->where('message', 'like', "%{$type}%")
            ->latest()
            ->first();

        return $lastAlert && now()->diffInMinutes($lastAlert->created_at) < 60;
    }
}
