<?php

namespace App\Services\Nodes;

use App\Models\Node;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * ServerUsagesSyncService - Sync server resource usage from Proxmox
 */
class ServerUsagesSyncService
{
    /**
     * Sync all servers on a node.
     */
    public function sync(Node $node): int
    {
        $client = new ProxmoxApiClient($node);
        $vms = $client->get("/nodes/{$node->cluster}/qemu");

        $synced = 0;

        foreach ($vms as $vm) {
            $server = Server::where('vmid', $vm['vmid'])
                ->where('node_id', $node->id)
                ->first();

            if ($server) {
                $server->update([
                    'last_cpu_usage' => ($vm['cpu'] ?? 0) * 100,
                    'last_memory_usage' => $vm['mem'] ?? 0,
                    'last_disk_read' => $vm['diskread'] ?? 0,
                    'last_disk_write' => $vm['diskwrite'] ?? 0,
                    'last_net_in' => $vm['netin'] ?? 0,
                    'last_net_out' => $vm['netout'] ?? 0,
                    'last_sync_at' => now(),
                ]);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Sync a single server.
     */
    public function syncServer(Server $server): void
    {
        $client = new ProxmoxApiClient($server->node);
        $status = $client->getVMStatus($server->vmid);

        $server->update([
            'last_cpu_usage' => ($status['cpu'] ?? 0) * 100,
            'last_memory_usage' => $status['mem'] ?? 0,
            'last_disk_read' => $status['diskread'] ?? 0,
            'last_disk_write' => $status['diskwrite'] ?? 0,
            'last_net_in' => $status['netin'] ?? 0,
            'last_net_out' => $status['netout'] ?? 0,
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Get aggregated stats for all servers on a node.
     */
    public function getNodeTotals(Node $node): array
    {
        $servers = Server::where('node_id', $node->id)->get();

        return [
            'total_servers' => $servers->count(),
            'total_cpu' => $servers->sum('cpu'),
            'total_memory' => $servers->sum('memory'),
            'total_disk' => $servers->sum('disk'),
            'avg_cpu_usage' => $servers->avg('last_cpu_usage') ?? 0,
        ];
    }
}
