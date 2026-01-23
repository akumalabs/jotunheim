<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * ServerSuspensionService - Manage server suspension
 */
class ServerSuspensionService
{
    /**
     * Suspend a server.
     */
    public function suspend(Server $server, ?string $reason = null): void
    {
        $client = new ProxmoxApiClient($server->node);
        $powerRepo = (new ProxmoxPowerRepository($client))->setServer($server);

        // Stop the VM if running
        try {
            $powerRepo->shutdown();
        } catch (\Exception $e) {
            // Ignore if already stopped
        }

        // Lock the VM (prevent start)
        $client->post("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/config", [
            'lock' => 'suspended',
        ]);

        // Update database
        $server->update([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);
    }

    /**
     * Unsuspend a server.
     */
    public function unsuspend(Server $server): void
    {
        $client = new ProxmoxApiClient($server->node);

        // Remove lock
        $client->post("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/config", [
            'delete' => 'lock',
        ]);

        // Update database
        $server->update([
            'is_suspended' => false,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
    }

    /**
     * Check if server is suspended in Proxmox.
     */
    public function isSuspendedInProxmox(Server $server): bool
    {
        $client = new ProxmoxApiClient($server->node);
        $config = $client->getVMConfig($server->vmid);

        return isset($config['lock']) && $config['lock'] === 'suspended';
    }
}
