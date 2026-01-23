<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxGuestAgentRepository - QEMU Guest Agent operations
 */
class ProxmoxGuestAgentRepository extends ProxmoxRepository
{
    /**
     * Execute guest-exec command.
     */
    public function exec(string $command, array $args = []): array
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/exec",
            [
                'command' => $command,
                'input-data' => implode(' ', $args),
            ]
        );
    }

    /**
     * Get file content from VM.
     */
    public function fileRead(string $file): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/file-read",
            ['file' => $file]
        );
    }

    /**
     * Write file to VM.
     */
    public function fileWrite(string $file, string $content): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/file-write",
            [
                'file' => $file,
                'content' => base64_encode($content),
            ]
        );
    }

    /**
     * Get network interfaces from guest.
     */
    public function getNetworkInterfaces(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/network-get-interfaces"
        );
    }

    /**
     * Get OS info from guest.
     */
    public function getOsInfo(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/get-osinfo"
        );
    }

    /**
     * Get host name from guest.
     */
    public function getHostname(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/get-host-name"
        );
    }

    /**
     * Get time from guest.
     */
    public function getTime(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/get-time"
        );
    }

    /**
     * Get memory info from guest.
     */
    public function getMemoryBlocks(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/get-memory-blocks"
        );
    }

    /**
     * Get info summary.
     */
    public function getInfo(): array
    {
        return $this->client->get(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/info"
        );
    }

    /**
     * Ping the guest agent.
     */
    public function ping(): bool
    {
        try {
            $this->client->post(
                "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/ping"
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Set user password via guest agent.
     */
    public function setUserPassword(string $username, string $password): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/set-user-password",
            [
                'username' => $username,
                'password' => $password,
            ]
        );
    }

    /**
     * Shutdown guest via agent.
     */
    public function shutdown(): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/shutdown"
        );
    }

    /**
     * Freeze filesystems.
     */
    public function fsfreezeFreeze(): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/fsfreeze-freeze"
        );
    }

    /**
     * Thaw filesystems.
     */
    public function fsfreezeThaw(): array|string
    {
        return $this->client->post(
            "/nodes/{$this->node->cluster}/qemu/{$this->server->vmid}/agent/fsfreeze-thaw"
        );
    }
}
