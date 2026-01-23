<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxCloudinitRepository - Dedicated cloudinit operations
 */
class ProxmoxCloudinitRepository extends ProxmoxRepository
{
    /**
     * Get cloud-init config.
     */
    public function getConfig(): array
    {
        $config = $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config"
        );

        // Extract cloud-init related fields
        $ciFields = ['ciuser', 'cipassword', 'sshkeys', 'ipconfig0', 'ipconfig1', 'nameserver', 'searchdomain'];
        $ciConfig = [];

        foreach ($ciFields as $field) {
            if (isset($config[$field])) {
                $ciConfig[$field] = $config[$field];
            }
        }

        return $ciConfig;
    }

    /**
     * Set cloud-init user.
     */
    public function setUser(string $username): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            ['ciuser' => $username]
        );
    }

    /**
     * Set cloud-init password.
     */
    public function setPassword(string $password): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            ['cipassword' => $password]
        );
    }

    /**
     * Set SSH keys (URL-encoded newline-separated).
     */
    public function setSshKeys(array $keys): array|string
    {
        $encoded = urlencode(implode("\n", $keys));

        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            ['sshkeys' => $encoded]
        );
    }

    /**
     * Set IP configuration.
     *
     * @param  string  $ip  e.g., '192.168.1.100/24'
     * @param  string  $gw  Gateway address
     * @param  int  $ifnum  Interface number (0 for ipconfig0)
     */
    public function setIpConfig(string $ip, string $gw, int $ifnum = 0): array|string
    {
        $value = "ip={$ip},gw={$gw}";

        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            ["ipconfig{$ifnum}" => $value]
        );
    }

    /**
     * Set DHCP for an interface.
     */
    public function setDhcp(int $ifnum = 0): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            ["ipconfig{$ifnum}" => 'ip=dhcp']
        );
    }

    /**
     * Set DNS servers.
     */
    public function setDns(array $nameservers, ?string $searchDomain = null): array|string
    {
        $params = ['nameserver' => implode(' ', $nameservers)];

        if ($searchDomain) {
            $params['searchdomain'] = $searchDomain;
        }

        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            $params
        );
    }

    /**
     * Regenerate cloud-init image.
     * Note: Proxmox doesn't have a direct regenerate endpoint.
     * Changes to config automatically trigger regeneration.
     */
    public function regenerate(): array|string
    {
        // No-op - config changes trigger automatic regeneration
        logger()->info("Cloud-init regeneration triggered by config changes");
        return [];
    }

    /**
     * Set full cloud-init configuration.
     */
    public function configure(array $config): array|string
    {
        $params = [];

        if (isset($config['user'])) {
            $params['ciuser'] = $config['user'];
        }
        if (isset($config['password'])) {
            $params['cipassword'] = $config['password'];
        }
        if (isset($config['ssh_keys'])) {
            $params['sshkeys'] = urlencode(implode("\n", $config['ssh_keys']));
        }
        if (isset($config['ip']) && isset($config['gateway'])) {
            $params['ipconfig0'] = "ip={$config['ip']},gw={$config['gateway']}";
        }
        if (isset($config['nameservers'])) {
            $params['nameserver'] = implode(' ', $config['nameservers']);
        }
        if (isset($config['searchdomain'])) {
            $params['searchdomain'] = $config['searchdomain'];
        }

        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/config",
            $params
        );
    }
}
