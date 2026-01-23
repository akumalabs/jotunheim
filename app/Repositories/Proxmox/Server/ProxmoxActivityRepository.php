<?php

namespace App\Repositories\Proxmox\Server;

use App\Repositories\Proxmox\ProxmoxRepository;

/**
 * ProxmoxActivityRepository - VM task monitoring
 */
class ProxmoxActivityRepository extends ProxmoxRepository
{
    /**
     * Get task status by UPID.
     */
    public function getTaskStatus(string $upid): array
    {
        return $this->client->get("/nodes/{$this->node->cluster}/tasks/{$upid}/status");
    }

    /**
     * Get task log.
     */
    public function getTaskLog(string $upid, int $start = 0, ?int $limit = null): array
    {
        $params = ['start' => $start];
        if ($limit) {
            $params['limit'] = $limit;
        }

        return $this->client->get("/nodes/{$this->node->cluster}/tasks/{$upid}/log", $params);
    }

    /**
     * Wait for task completion.
     */
    public function waitForTask(string $upid, int $timeout = 300): array
    {
        $start = time();

        while (time() - $start < $timeout) {
            $status = $this->getTaskStatus($upid);

            if ($status['status'] === 'stopped') {
                return $status;
            }

            sleep(2);
        }

        throw new \Exception("Task {$upid} did not complete within {$timeout} seconds");
    }

    /**
     * Check if task is running.
     */
    public function isTaskRunning(string $upid): bool
    {
        $status = $this->getTaskStatus($upid);

        return $status['status'] === 'running';
    }

    /**
     * Check if task succeeded.
     */
    public function taskSucceeded(string $upid): bool
    {
        $status = $this->getTaskStatus($upid);

        return $status['status'] === 'stopped' && $status['exitstatus'] === 'OK';
    }

    /**
     * Get recent tasks for a VM.
     */
    public function getVmTasks(int $vmid, int $limit = 50): array
    {
        $tasks = $this->client->get("/nodes/{$this->node->cluster}/tasks", [
            'vmid' => $vmid,
            'limit' => $limit,
        ]);

        return $tasks;
    }

    /**
     * Stop a running task.
     */
    public function stopTask(string $upid): array|string
    {
        return $this->client->delete("/nodes/{$this->node->cluster}/tasks/{$upid}");
    }
}
