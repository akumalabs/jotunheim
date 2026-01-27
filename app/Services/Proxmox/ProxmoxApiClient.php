<?php

namespace App\Services\Proxmox;

use App\Models\Node;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ProxmoxApiClient
{
    protected Node $node;

    protected PendingRequest $client;

    public function __construct(Node $node)
    {
        $this->node = $node;
        $this->client = $this->createClient();
    }

    /**
     * Create HTTP client with Proxmox authentication.
     * Uses REST API v2 format with JSON content type.
     */
    protected function createClient(): PendingRequest
    {
        $verify = env('PROXMOX_VERIFY_SSL', true);

        return Http::baseUrl($this->node->getApiUrl())
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "PVEAPIToken={$this->node->token_id}={$this->node->token_secret}",
            ])
            ->withOptions([
                'verify' => $verify,
                'timeout' => env('PROXMOX_API_TIMEOUT', 30),
                'connect_timeout' => env('PROXMOX_CONNECT_TIMEOUT', 5),
            ]);
    }

    /**
     * Make a GET request to the Proxmox API.
     */
    public function get(string $path, array $query = []): array|string
    {
        $response = $this->client->get($path, $query);

        return $this->handleResponse($response);
    }

    /**
     * Make a POST request to the Proxmox API.
     */
    public function post(string $path, array $data = []): array|string
    {
        $response = $this->client->post($path, $data);

        return $this->handleResponse($response);
    }

    /**
     * Make a PUT request to the Proxmox API.
     */
    public function put(string $path, array $data = []): array|string
    {
        $response = $this->client->put($path, $data);

        return $this->handleResponse($response);
    }

    /**
     * Make a DELETE request to the Proxmox API.
     */
    public function delete(string $path, array $data = []): array|string
    {
        $response = $this->client->delete($path, $data);

        return $this->handleResponse($response);
    }

    /**
     * Handle the API response.
     * Proxmox may return array (like VM status) or string (like task UPID for clone)
     */
    protected function handleResponse(Response $response): array|string
    {
        if (! $response->successful()) {
            throw new ProxmoxApiException(
                $response->json('errors') ?? $response->body(),
                $response->status()
            );
        }

        $data = $response->json('data');

        // Return data as-is (could be array, string, or null)
        return $data ?? [];
    }

    /**
     * Get the node being used by this client.
     */
    public function getNode(): Node
    {
        return $this->node;
    }

    // =====================
    // Node/Cluster Methods
    // =====================

    /**
     * Get cluster status and resources.
     */
    public function getClusterStatus(): array
    {
        return $this->get('/cluster/status');
    }

    /**
     * Get cluster resources (VMs, storage, etc).
     */
    public function getClusterResources(?string $type = null): array
    {
        $query = $type ? ['type' => $type] : [];

        return $this->get('/cluster/resources', $query);
    }

    /**
     * Get node status.
     */
    public function getNodeStatus(?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/status");
    }

    /**
     * Get list of nodes in the cluster.
     */
    public function getNodes(): array
    {
        return $this->get('/nodes');
    }

    // =====================
    // VM Methods
    // =====================

    /**
     * Get list of VMs on a node.
     */
    public function getVMs(?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/qemu");
    }

    /**
     * Get VM status and configuration.
     */
    public function getVMStatus(int $vmid, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/qemu/{$vmid}/status/current");
    }

    /**
     * Check if a VMID already exists on Proxmox (including ghost configs).
     */
    public function vmidExists(int $vmid, ?string $nodeName = null): bool
    {
        try {
            // Try to get VM config - this will fail if VM doesn't exist at all
            $this->getVMConfig($vmid, $nodeName);
            return true; // Config exists (VM or ghost)
        } catch (ProxmoxApiException $e) {
            // Check error message
            $message = $e->getMessage();
            // These indicate VM definitely doesn't exist
            if (str_contains($message, 'does not exist') || 
                str_contains($message, 'not exist') ||
                str_contains($message, 'No such file') ||
                str_contains($message, '500')) {
                return false;
            }
            // For ambiguous errors, assume exists to be safe
            return true;
        } catch (\Exception $e) {
            // Unknown error, assume exists to be safe
            return true;
        }
    }

    /**
     * Get VM configuration.
     */
    public function getVMConfig(int $vmid, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/qemu/{$vmid}/config");
    }

    /**
     * Start a VM. Returns UPID.
     */
    public function startVM(int $vmid, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/status/start", ['timeout' => 30]);
    }

    /**
     * Stop a VM (force kill). Returns UPID.
     */
    public function stopVM(int $vmid, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/status/stop", ['timeout' => 30]);
    }

    /**
     * Shutdown a VM (graceful). Returns UPID.
     */
    public function shutdownVM(int $vmid, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/status/shutdown", ['timeout' => 30]);
    }

    /**
     * Reboot a VM (graceful restart). Returns UPID.
     */
    public function rebootVM(int $vmid, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/status/reboot", ['timeout' => 30]);
    }

    /**
     * Reset a VM (hard reboot). Returns UPID.
     */
    public function resetVM(int $vmid, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/status/reset");
    }

    /**
     * Clone a VM from template.
     * Returns UPID string for the clone task.
     */
    public function cloneVM(int $templateVmid, int $newVmid, array $options = [], ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();
        $data = array_merge([
            'newid' => $newVmid,
            'full' => 1, // Full clone, not linked
        ], $options);

        return $this->post("/nodes/{$nodeName}/qemu/{$templateVmid}/clone", $data);
    }

    /**
     * Delete a VM. Returns UPID.
     */
    public function deleteVM(int $vmid, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->delete("/nodes/{$nodeName}/qemu/{$vmid}");
    }

    /**
     * Update VM configuration.
     */
    public function updateVMConfig(int $vmid, array $config, ?string $nodeName = null): array|string
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/config", $config);
    }

    /**
     * Resize a VM disk.
     * Returns immediately without waiting for task completion (fire-and-forget).
     */
    public function resizeDisk(int $vmid, string $disk, int $sizeBytes, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        $kibibytes = (int) floor($sizeBytes / 1024);

        $response = $this->put("/nodes/{$nodeName}/qemu/{$vmid}/resize", [
            'disk' => $disk,
            'size' => "{$kibibytes}K",
        ]);

        return $response['data'] ?? [];
    }

    // =====================
    // Console Methods
    // =====================

    /**
     * Get VNC proxy ticket for noVNC console.
     */
    public function getVNCProxy(int $vmid, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/vncproxy", [
            'websocket' => 1,
        ]);
    }

    /**
     * Get SPICE proxy ticket.
     */
    public function getSpiceProxy(int $vmid, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu/{$vmid}/spiceproxy");
    }

    // =====================
    // Storage Methods
    // =====================

    /**
     * Get storage list on a node.
     */
    public function getStorage(?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/storage");
    }

    /**
     * Get storage content (ISOs, templates, etc).
     */
    public function getStorageContent(string $storage, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/storage/{$storage}/content");
    }

    // =====================
    // Template Methods
    // =====================

    /**
     * Get available templates (VMs marked as templates).
     */
    public function getTemplates(?string $nodeName = null): array
    {
        $vms = $this->getVMs($nodeName);

        return array_filter($vms, fn ($vm) => ($vm['template'] ?? 0) === 1);
    }

    // =====================
    // Backup Methods
    // =====================

    /**
     * Create a backup of a VM.
     */
    public function createBackup(int $vmid, string $storage, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/vzdump", [
            'vmid' => $vmid,
            'storage' => $storage,
            'mode' => 'snapshot',
            'compress' => 'zstd',
        ]);
    }

    /**
     * Delete a backup.
     */
    public function deleteBackup(string $volid, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();
        // Parse storage from volid (format: storage:backup/vzdump-qemu-XXX.vma.zst)
        $parts = explode(':', $volid);
        $storage = $parts[0];

        return $this->delete("/nodes/{$nodeName}/storage/{$storage}/content/{$volid}");
    }

    /**
     * Restore a VM from backup.
     */
    public function restoreBackup(int $vmid, string $archive, string $storage, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->post("/nodes/{$nodeName}/qemu", [
            'vmid' => $vmid,
            'archive' => $archive,
            'storage' => $storage,
        ]);
    }

    // =====================
    // Task Methods
    // =====================

    /**
     * Get task status.
     */
    public function getTaskStatus(string $taskId, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        return $this->get("/nodes/{$nodeName}/tasks/{$taskId}/status?_t=" . time());
    }

    /**
     * Get task log.
     */
    public function getTaskLog(string $taskId, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();

        // Proxmox returns list of {n: line_num, t: text}
        // Force fresh response with timestamp to prevent stalled progress logs
        return $this->get("/nodes/{$nodeName}/tasks/{$taskId}/log?_t=" . time());
    }

    /**
     * Wait for a task to complete.
     */
    public function waitForTask(string $taskId, int $timeout = 300, ?string $nodeName = null): array
    {
        $nodeName = $nodeName ?? $this->getProxmoxNodeName();
        $start = time();

        while (time() - $start < $timeout) {
            $status = $this->getTaskStatus($taskId, $nodeName);

            if ($status['status'] === 'stopped') {
                if (($status['exitstatus'] ?? 'OK') !== 'OK') {
                    throw new ProxmoxApiException("Task {$taskId} failed: " . $status['exitstatus']);
                }
                return $status;
            }

            sleep(2);
        }

        throw new ProxmoxApiException("Task {$taskId} timed out after {$timeout} seconds");
    }

    // =====================
    // Helper Methods
    // =====================

    /**
     * Get the Proxmox node name from the first node in cluster.
     */
    protected function getProxmoxNodeName(): string
    {
        // Use cluster name if set, otherwise get from API
        if ($this->node->cluster) {
            return $this->node->cluster;
        }

        // Get from API
        $nodes = $this->getNodes();
        if (empty($nodes)) {
            throw new ProxmoxApiException('No nodes found in Proxmox cluster');
        }

        return $nodes[0]['node'];
    }

    /**
     * Get next available VMID.
     */
    public function getNextVmid(): int
    {
        $response = $this->get('/cluster/nextid');

        return (int) $response;
    }
}
