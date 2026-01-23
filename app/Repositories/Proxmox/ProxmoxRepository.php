<?php

namespace App\Repositories\Proxmox;

use App\Models\Node;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;

/**
 * Provides common functionality for Proxmox API interactions
 */
abstract class ProxmoxRepository
{
    protected ProxmoxApiClient $client;

    protected ?Node $node = null;

    protected ?Server $server = null;

    public function __construct(ProxmoxApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Set the node context
     */
    public function setNode(Node $node): static
    {
        $this->node = $node;
        // Client already has node from constructor

        return $this;
    }

    /**
     * Set the server context (automatically sets node)
     */
    public function setServer(Server $server): static
    {
        $this->server = $server;
        $this->node = $server->node;
        // Client already has node from constructor

        return $this;
    }

    /**
     * Get the VM path for API calls
     */
    protected function vmPath(string $endpoint = ''): string
    {
        if (! $this->server) {
            throw new \RuntimeException('Server context not set');
        }

        $base = "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}";

        return $endpoint ? "{$base}/{$endpoint}" : $base;
    }

    /**
     * Get the node path for API calls
     */
    protected function nodePath(string $endpoint = ''): string
    {
        if (! $this->node) {
            throw new \RuntimeException('Node context not set');
        }

        $base = "/nodes/{$this->node->cluster}";

        return $endpoint ? "{$base}/{$endpoint}" : $base;
    }
}
