<?php

namespace App\Repositories\Proxmox;

use App\Models\Node;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Webmozart\Assert\Assert;

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
     * Set node context
     */
    public function setNode(Node $node): static
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Set server context (automatically sets node)
     */
    public function setServer(Server $server): static
    {
        $this->server = $server;
        $this->node = $server->node;

        return $this;
    }

    /**
     * Get server instance with assertion
     */
    protected function requireServer(): Server
    {
        Assert::isInstanceOf(
            $this->server,
            Server::class,
            'Server is not set or invalid.'
        );

        return $this->server;
    }

    /**
     * Get node instance with assertion
     */
    protected function requireNode(): Node
    {
        Assert::isInstanceOf(
            $this->node,
            Node::class,
            'Node is not set or invalid.'
        );

        return $this->node;
    }

    /**
     * Get HTTP client
     */
    protected function getClient(): ProxmoxApiClient
    {
        return $this->client;
    }

    /**
     * Get VM path for API calls
     */
    protected function vmPath(string $endpoint = ''): string
    {
        $server = $this->requireServer();
        $node = $this->requireNode();

        $base = "/nodes/{$node->cluster}/qemu/{$server->vmid}";

        return $endpoint ? "{$base}/{$endpoint}" : $base;
    }

    /**
     * Get node path for API calls
     */
    protected function nodePath(string $endpoint = ''): string
    {
        $node = $this->requireNode();

        $base = "/nodes/{$node->cluster}";

        return $endpoint ? "{$base}/{$endpoint}" : $base;
    }

    /**
     * Extract data from API response
     */
    protected function getData(array $response): mixed
    {
        return $response['data'] ?? $response;
    }
}
