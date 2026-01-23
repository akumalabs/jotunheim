<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;

class CloudinitService
{
    public function __construct(
        protected ProxmoxCloudinitRepository $cloudinitRepository
    ) {}

    /**
     * Configure cloud-init for a server.
     */
    public function configure(Server $server, array $config): void
    {
        $cloudinitConfig = $this->buildConfig($server, $config);
        $this->cloudinitRepository->configure($server, $cloudinitConfig);
    }

    /**
     * Update user password.
     */
    public function setPassword(Server $server, string $password): void
    {
        $this->cloudinitRepository->configure($server, [
            'cipassword' => $password,
        ]);
    }

    /**
     * Update SSH keys.
     */
    public function setSshKeys(Server $server, array $keys): void
    {
        $sshKeysString = implode("\n", $keys);
        $this->cloudinitRepository->configure($server, [
            'sshkeys' => urlencode($sshKeysString),
        ]);
    }

    /**
     * Update network configuration.
     */
    public function setNetwork(Server $server, array $network): void
    {
        $config = [];

        if (isset($network['ip'])) {
            $config['ipconfig0'] = sprintf(
                'ip=%s/%s,gw=%s',
                $network['ip'],
                $network['cidr'] ?? 24,
                $network['gateway']
            );
        }

        if (isset($network['ip6'])) {
            $config['ipconfig0'] = ($config['ipconfig0'] ?? '').sprintf(
                ',ip6=%s/%s,gw6=%s',
                $network['ip6'],
                $network['cidr6'] ?? 64,
                $network['gateway6']
            );
        }

        if (! empty($config)) {
            $this->cloudinitRepository->configure($server, $config);
        }
    }

    /**
     * Build full cloud-init config from server and custom config.
     */
    protected function buildConfig(Server $server, array $config): array
    {
        $cloudinitConfig = [];

        // User configuration
        if (isset($config['user'])) {
            $cloudinitConfig['ciuser'] = $config['user'];
        }

        if (isset($config['password'])) {
            $cloudinitConfig['cipassword'] = $config['password'];
        }

        if (isset($config['ssh_keys'])) {
            $cloudinitConfig['sshkeys'] = urlencode(implode("\n", $config['ssh_keys']));
        }

        // Network configuration
        if (isset($config['ip']) || isset($config['ip6'])) {
            $ipconfig = [];

            if (isset($config['ip'])) {
                $ipconfig[] = sprintf(
                    'ip=%s/%s,gw=%s',
                    $config['ip'],
                    $config['cidr'] ?? 24,
                    $config['gateway'] ?? ''
                );
            }

            if (isset($config['ip6'])) {
                $ipconfig[] = sprintf(
                    'ip6=%s/%s,gw6=%s',
                    $config['ip6'],
                    $config['cidr6'] ?? 64,
                    $config['gateway6'] ?? ''
                );
            }

            $cloudinitConfig['ipconfig0'] = implode(',', $ipconfig);
        }

        // DNS
        if (isset($config['nameserver'])) {
            $cloudinitConfig['nameserver'] = $config['nameserver'];
        }

        if (isset($config['searchdomain'])) {
            $cloudinitConfig['searchdomain'] = $config['searchdomain'];
        }

        return $cloudinitConfig;
    }

    /**
     * Regenerate cloud-init image.
     */
    public function regenerate(Server $server): void
    {
        $this->cloudinitRepository->regenerate($server);
    }
}
