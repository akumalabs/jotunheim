<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxFirewallRepository - VM firewall management
 */
class ProxmoxFirewallRepository extends ProxmoxRepository
{
    /**
     * Get firewall options.
     */
    public function getOptions(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/options"
        );
    }

    /**
     * Set firewall options.
     */
    public function setOptions(array $options): array|string
    {
        return $this->client->put(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/options",
            $options
        );
    }

    /**
     * Enable firewall.
     */
    public function enable(): array|string
    {
        return $this->setOptions(['enable' => 1]);
    }

    /**
     * Disable firewall.
     */
    public function disable(): array|string
    {
        return $this->setOptions(['enable' => 0]);
    }

    /**
     * Get all firewall rules.
     */
    public function getRules(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/rules"
        );
    }

    /**
     * Get a specific rule.
     */
    public function getRule(int $pos): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/rules/{$pos}"
        );
    }

    /**
     * Create a firewall rule.
     */
    public function createRule(array $rule): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/rules",
            $rule
        );
    }

    /**
     * Update a firewall rule.
     */
    public function updateRule(int $pos, array $rule): array|string
    {
        return $this->client->put(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/rules/{$pos}",
            $rule
        );
    }

    /**
     * Delete a firewall rule.
     */
    public function deleteRule(int $pos): array|string
    {
        return $this->client->delete(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/rules/{$pos}"
        );
    }

    /**
     * Create common rules for a VM.
     */
    public function createDefaultRules(): void
    {
        // Allow SSH
        $this->createRule([
            'type' => 'in',
            'action' => 'ACCEPT',
            'proto' => 'tcp',
            'dport' => '22',
            'comment' => 'Allow SSH',
        ]);

        // Allow HTTP
        $this->createRule([
            'type' => 'in',
            'action' => 'ACCEPT',
            'proto' => 'tcp',
            'dport' => '80',
            'comment' => 'Allow HTTP',
        ]);

        // Allow HTTPS
        $this->createRule([
            'type' => 'in',
            'action' => 'ACCEPT',
            'proto' => 'tcp',
            'dport' => '443',
            'comment' => 'Allow HTTPS',
        ]);
    }

    /**
     * Get IP sets.
     */
    public function getIpsets(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/ipset"
        );
    }

    /**
     * Get aliases.
     */
    public function getAliases(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/firewall/aliases"
        );
    }
}
