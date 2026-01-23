<?php

namespace App\Repositories\Proxmox\Server;

use App\Enums\Server\PowerCommand;
use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * Handles all VM power operations
 */
class ProxmoxPowerRepository extends ProxmoxRepository
{
    /**
     * Send a power command to the VM
     *
     * @param  PowerCommand  $command  The power command to send
     * @param  int  $timeout  Timeout for graceful operations (seconds)
     * @return string UPID of the task
     */
    public function send(PowerCommand $command, int $timeout = 30): string
    {
        $endpoint = $this->vmPath("status/{$command->getProxmoxEndpoint()}");

        $params = [];
        if ($command->requiresTimeout()) {
            $params['timeout'] = $timeout;
        }

        $result = $this->client->post($endpoint, $params);

        return is_string($result) ? $result : ($result['data'] ?? '');
    }

    /**
     * Start the VM
     */
    public function start(): string
    {
        return $this->send(PowerCommand::START);
    }

    /**
     * Gracefully shutdown the VM
     */
    public function shutdown(int $timeout = 30): string
    {
        return $this->send(PowerCommand::SHUTDOWN, $timeout);
    }

    /**
     * Gracefully reboot the VM
     */
    public function reboot(int $timeout = 30): string
    {
        return $this->send(PowerCommand::REBOOT, $timeout);
    }

    /**
     * Stop the VM (Force Stop)
     */
    public function stop(): string
    {
        return $this->send(PowerCommand::STOP);
    }

    /**
     * Force stop the VM (kill)
     */
    public function kill(): string
    {
        return $this->send(PowerCommand::KILL);
    }
}
