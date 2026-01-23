<?php

namespace App\Repositories\Proxmox\Server;

use App\Data\Server\ServerStateData;
use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * Handles VM status and state operations
 */
class ProxmoxServerRepository extends ProxmoxRepository
{
    /**
     * Get the current state of the VM
     */
    public function getState(): ServerStateData
    {
        $response = $this->client->get($this->vmPath('status/current'));

        $data = is_array($response) ? $response : [];

        return ServerStateData::fromProxmox($data);
    }

    /**
     * Get the VM configuration
     */
    public function getConfig(): array
    {
        $response = $this->client->get($this->vmPath('config'));

        return is_array($response) ? $response : [];
    }

    /**
     * Check if VM exists
     */
    public function exists(): bool
    {
        try {
            $this->getState();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if VM has a lock
     */
    public function isLocked(): bool
    {
        $config = $this->getConfig();

        return isset($config['lock']);
    }

    /**
     * Wait until VM is unlocked
     *
     * @param  int  $maxAttempts  Maximum number of attempts
     * @param  int  $interval  Seconds between attempts
     * @return bool True if unlocked, false if timed out
     */
    public function waitUntilUnlocked(int $maxAttempts = 60, int $interval = 2): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (! $this->isLocked()) {
                return true;
            }
            sleep($interval);
        }

        return false;
    }
}
