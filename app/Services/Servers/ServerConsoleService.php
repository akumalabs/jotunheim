<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxConsoleRepository;

class ServerConsoleService
{
    public function __construct(
        protected ProxmoxConsoleRepository $consoleRepository
    ) {}

    /**
     * Get noVNC console credentials.
     */
    public function getNoVncCredentials(Server $server): array
    {
        return $this->consoleRepository->createVncProxy($server);
    }

    /**
     * Get xterm.js console credentials.
     */
    public function getXtermCredentials(Server $server): array
    {
        return $this->consoleRepository->createTermProxy($server);
    }

    /**
     * Build noVNC URL for direct console access.
     */
    public function buildNoVncUrl(Server $server): string
    {
        $node = $server->node;
        $credentials = $this->getNoVncCredentials($server);

        return sprintf(
            'https://%s:%d/?console=kvm&novnc=1&vmid=%d&vmname=%s&node=%s&resize=off&port=%s&vncticket=%s',
            $node->fqdn,
            $node->port ?? 8006,
            $server->vmid,
            urlencode($server->name),
            $node->cluster,
            $credentials['port'],
            urlencode($credentials['ticket'])
        );
    }

    /**
     * Send keyboard input to console (for VNC).
     */
    public function sendKeys(Server $server, string $keys): void
    {
        $this->consoleRepository->sendKeys($server, $keys);
    }
}
