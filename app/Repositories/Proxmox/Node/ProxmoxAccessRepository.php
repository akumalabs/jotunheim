<?php

namespace App\Repositories\Proxmox\Node;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxAccessRepository - Manage PVE users for noVNC access
 */
class ProxmoxAccessRepository extends ProxmoxRepository
{
    /**
     * Get all users on the node.
     */
    public function getUsers(): array
    {
        return $this->client->get('/access/users');
    }

    /**
     * Create a user for VM console access.
     */
    public function createUser(string $username, string $password, ?string $comment = null): array|string
    {
        return $this->client->post('/access/users', [
            'userid' => "{$username}@pve",
            'password' => $password,
            'comment' => $comment ?? 'Midgard managed user',
        ]);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(string $username): array|string
    {
        return $this->client->delete("/access/users/{$username}@pve");
    }

    /**
     * Set user password.
     */
    public function setPassword(string $username, string $password): array|string
    {
        return $this->client->put('/access/password', [
            'userid' => "{$username}@pve",
            'password' => $password,
        ]);
    }

    /**
     * Create access ticket for noVNC.
     */
    public function createTicket(string $username, string $password): array
    {
        return $this->client->post('/access/ticket', [
            'username' => "{$username}@pve",
            'password' => $password,
        ]);
    }

    /**
     * Grant user permissions on a VM.
     */
    public function grantVmAccess(string $username, int $vmid): array|string
    {
        return $this->client->put('/access/acl', [
            'path' => "/vms/{$vmid}",
            'users' => "{$username}@pve",
            'roles' => 'PVEVMUser',
        ]);
    }

    /**
     * Revoke user permissions.
     */
    public function revokeVmAccess(string $username, int $vmid): array|string
    {
        return $this->client->put('/access/acl', [
            'path' => "/vms/{$vmid}",
            'users' => "{$username}@pve",
            'roles' => 'PVEVMUser',
            'delete' => 1,
        ]);
    }
}
