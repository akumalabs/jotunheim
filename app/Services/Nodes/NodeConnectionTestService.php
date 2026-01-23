<?php

namespace App\Services\Nodes;

use App\Models\Node;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * NodeConnectionTestService - Test node connectivity
 */
class NodeConnectionTestService
{
    /**
     * Test connection to a node.
     */
    public function test(Node $node): array
    {
        $start = microtime(true);

        try {
            $client = new ProxmoxApiClient($node);
            $version = $client->get('/version');

            $latency = round((microtime(true) - $start) * 1000, 2); // ms

            return [
                'success' => true,
                'latency_ms' => $latency,
                'version' => $version['version'] ?? 'Unknown',
                'release' => $version['release'] ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * Check if node is reachable.
     */
    public function isReachable(Node $node): bool
    {
        $result = $this->test($node);

        return $result['success'];
    }

    /**
     * Get node version info.
     */
    public function getVersion(Node $node): ?array
    {
        try {
            $client = new ProxmoxApiClient($node);

            return $client->get('/version');
        } catch (\Exception $e) {
            return null;
        }
    }
}
