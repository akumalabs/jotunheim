<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Repositories\Proxmox\Node\ProxmoxNodeRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    /**
     * List all nodes.
     */
    public function index(Request $request): JsonResponse
    {
        $nodes = Node::with('location')
            ->withCount('servers')
            ->get()
            ->map(fn ($node) => $this->formatNode($node));

        return response()->json([
            'data' => $nodes,
        ]);
    }

    /**
     * Get a single node.
     */
    public function show(Node $node): JsonResponse
    {
        $node->load('location');
        $node->loadCount('servers');

        return response()->json([
            'data' => $this->formatNode($node),
        ]);
    }

    /**
     * Create a new node.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:255'],
            'fqdn' => ['required', 'string', 'max:255'],
            'port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'token_id' => ['required', 'string'],
            'token_secret' => ['required', 'string'],
            'memory' => ['sometimes', 'integer', 'min:0'],
            'memory_overallocate' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'disk' => ['sometimes', 'integer', 'min:0'],
            'disk_overallocate' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'cpu' => ['sometimes', 'integer', 'min:0'],
            'cpu_overallocate' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'storage' => ['sometimes', 'string', 'max:255'],
            'network' => ['sometimes', 'string', 'max:255'],
            'cluster' => ['nullable', 'string', 'max:255'],
        ]);

        $node = Node::create($validated);
        $node->load('location');

        return response()->json([
            'message' => 'Node created successfully',
            'data' => $this->formatNode($node),
        ], 201);
    }

    /**
     * Update a node.
     */
    public function update(Request $request, Node $node): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['sometimes', 'exists:locations,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'fqdn' => ['sometimes', 'string', 'max:255'],
            'port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'token_id' => ['sometimes', 'string'],
            'token_secret' => ['sometimes', 'string'],
            'memory' => ['sometimes', 'integer', 'min:0'],
            'memory_overallocate' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'disk' => ['sometimes', 'integer', 'min:0'],
            'disk_overallocate' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'cpu' => ['sometimes', 'integer', 'min:0'],
            'cpu_overallocate' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'storage' => ['sometimes', 'string', 'max:255'],
            'network' => ['sometimes', 'string', 'max:255'],
            'cluster' => ['nullable', 'string', 'max:255'],
            'maintenance_mode' => ['sometimes', 'boolean'],
        ]);

        $node->update($validated);
        $node->load('location');

        return response()->json([
            'message' => 'Node updated successfully',
            'data' => $this->formatNode($node),
        ]);
    }

    /**
     * Delete a node.
     */
    public function destroy(Node $node): JsonResponse
    {
        // Check if node has servers
        if ($node->servers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete node with active servers. Please migrate or delete servers first.',
            ], 422);
        }

        $node->delete();

        return response()->json([
            'message' => 'Node deleted successfully',
        ]);
    }

    /**
     * Test connection to a node.
     */
    public function testConnection(Node $node): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($node);
            $status = $client->getNodeStatus();

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'uptime' => $status['uptime'] ?? null,
                    'cpu' => $status['cpu'] ?? null,
                    'memory' => $status['memory'] ?? null,
                ],
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sync node resources from Proxmox.
     */
    public function sync(Node $node): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($node);
            $status = $client->getNodeStatus();

            // Update node with actual resources
            $node->update([
                'memory' => $status['memory']['total'] ?? $node->memory,
                'disk' => $status['rootfs']['total'] ?? $node->disk,
                'cpu' => $status['cpuinfo']['cpus'] ?? $node->cpu,
            ]);

            // Get cluster name if not set
            if (! $node->cluster) {
                $nodes = $client->getNodes();
                if (! empty($nodes)) {
                    $node->update(['cluster' => $nodes[0]['node']]);
                }
            }

            return response()->json([
                'message' => 'Node synced successfully',
                'data' => $this->formatNode($node->fresh('location')),
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Sync failed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get node statistics from Proxmox.
     */
    public function stats(Node $node): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($node);
            $repository = (new ProxmoxNodeRepository($client))->setNode($node);
            $status = $repository->getStatus();

            // Also get storage info
            $storages = $repository->getStorages();

            return response()->json([
                'data' => [
                    'status' => $status->status,
                    'uptime' => $status->uptime,
                    'uptime_formatted' => $status->uptimeFormatted(),
                    'cpu' => [
                        'usage' => $status->cpuPercent(),
                        'cores' => $status->cpuCores,
                    ],
                    'memory' => [
                        'used' => $status->memoryUsed,
                        'total' => $status->memoryTotal,
                        'free' => $status->memoryFree,
                        'usage' => $status->memoryPercent(),
                    ],
                    'storages' => array_map(fn ($s) => [
                        'name' => $s->storage,
                        'type' => $s->type,
                        'used' => $s->used,
                        'total' => $s->total,
                        'usage' => $s->usagePercent(),
                        'supports_images' => $s->supportsImages(),
                        'supports_backup' => $s->supportsBackup(),
                    ], $storages),
                ],
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to get stats',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format node for API response.
     */
    protected function formatNode(Node $node): array
    {
        return [
            'id' => $node->id,
            'uuid' => $node->uuid,
            'name' => $node->name,
            'fqdn' => $node->fqdn,
            'port' => $node->port,
            'cluster' => $node->cluster,
            'storage' => $node->storage,
            'network' => $node->network,
            'memory' => $node->memory,
            'memory_overallocate' => $node->memory_overallocate,
            'disk' => $node->disk,
            'disk_overallocate' => $node->disk_overallocate,
            'cpu' => $node->cpu,
            'cpu_overallocate' => $node->cpu_overallocate,
            'maintenance_mode' => $node->maintenance_mode,
            'servers_count' => $node->servers_count ?? 0,
            'location' => $node->location ? [
                'id' => $node->location->id,
                'name' => $node->location->name,
                'short_code' => $node->location->short_code,
            ] : null,
            'created_at' => $node->created_at,
            'updated_at' => $node->updated_at,
        ];
    }
}
