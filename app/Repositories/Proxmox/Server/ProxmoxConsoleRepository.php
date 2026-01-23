<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * Handles VNC/terminal console operations
 */
class ProxmoxConsoleRepository extends ProxmoxRepository
{
    /**
     * Get noVNC credentials for web console
     */
    public function getNoVncCredentials(): array
    {
        $response = $this->client->post($this->vmPath('vncproxy'));

        return [
            'ticket' => $response['ticket'] ?? null,
            'port' => $response['port'] ?? null,
            'user' => $response['user'] ?? 'root@pam',
            'node' => $this->node->fqdn ?? $this->node->cluster,
        ];
    }

    /**
     * Get xterm.js credentials for terminal console
     */
    public function getXtermCredentials(): array
    {
        $response = $this->client->post($this->vmPath('termproxy'));

        return [
            'ticket' => $response['ticket'] ?? null,
            'port' => $response['port'] ?? null,
            'user' => $response['user'] ?? 'root@pam',
            'node' => $this->node->fqdn ?? $this->node->cluster,
        ];
    }

    /**
     * Build WebSocket URL for noVNC
     */
    public function getNoVncUrl(): string
    {
        $creds = $this->getNoVncCredentials();
        $host = $this->node->fqdn ?? $this->node->cluster;

        return "wss://{$host}:{$creds['port']}/?vncticket=".urlencode($creds['ticket']);
    }

    /**
     * Build WebSocket URL for xterm
     */
    public function getXtermUrl(): string
    {
        $creds = $this->getXtermCredentials();
        $host = $this->node->fqdn ?? $this->node->cluster;

        return "wss://{$host}/api2/json/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/vncwebsocket?port={$creds['port']}&vncticket=".urlencode($creds['ticket']);
    }
}
