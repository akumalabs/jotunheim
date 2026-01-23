<?php

namespace App\Repositories\Proxmox\Node;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxAllocationRepository - VMID allocation management
 */
class ProxmoxAllocationRepository extends ProxmoxRepository
{
    /**
     * Get next available VMID.
     */
    public function getNextVmid(): int
    {
        $result = $this->client->get('/cluster/nextid');

        return (int) $result;
    }

    /**
     * Get all VMs on this node.
     */
    public function getVms(): array
    {
        return $this->client->get("/nodes/{$this->node->cluster}/qemu");
    }

    /**
     * Check if VMID exists on this node.
     */
    public function vmidExists(int $vmid): bool
    {
        $vms = $this->getVms();
        foreach ($vms as $vm) {
            if ($vm['vmid'] === $vmid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get templates available for cloning.
     */
    public function getTemplates(): array
    {
        $vms = $this->getVms();

        return array_filter($vms, fn ($vm) => isset($vm['template']) && $vm['template'] == 1);
    }

    /**
     * Get used VMIDs range summary.
     */
    public function getVmidRange(): array
    {
        $vms = $this->getVms();
        $vmids = array_column($vms, 'vmid');

        if (empty($vmids)) {
            return ['min' => null, 'max' => null, 'count' => 0];
        }

        return [
            'min' => min($vmids),
            'max' => max($vmids),
            'count' => count($vmids),
        ];
    }
}
