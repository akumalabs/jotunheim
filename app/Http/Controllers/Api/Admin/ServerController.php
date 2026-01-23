<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Rebuild\RebuildStep;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ServerController extends Controller
{
    /**
     * List all servers (admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Server::with(['user', 'node.location', 'addresses']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by node
        if ($request->has('node_id')) {
            $query->where('node_id', $request->node_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $servers = $query->orderBy('created_at', 'desc')->get()
            ->map(fn ($server) => $this->formatServer($server));

        return response()->json([
            'data' => $servers,
        ]);
    }

    /**
     * Get a single server.
     */
    public function show(Server $server): JsonResponse
    {
        $server->load(['user', 'node.location', 'addresses']);

        return response()->json([
            'data' => $this->formatServer($server, true),
        ]);
    }

    /**
     * Create a new server.
     */
    /**
     * List unmanaged VMs on a node (adoption candidates).
     */
    public function unmanaged(Node $node): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($node);
            $resources = $client->getClusterResources(); // get all cluster resources or specific node?
            
            // Detect unique nodes in the cluster
            $pveNodes = array_unique(array_map(fn($r) => $r['node'] ?? null, $resources));
            $pveNodes = array_filter($pveNodes); // Remove nulls
            
            $targetNode = $node->name;

            // If only one node exists in PVE, assume it matches our $node regardless of name mismatch
            if (count($pveNodes) === 1) {
                $targetNode = reset($pveNodes);
            }
            
            // Filter QEMU/LXC on this node
            $nodeVms = array_filter($resources, fn($r) => 
                ($r['type'] === 'qemu' || $r['type'] === 'lxc') && 
                ($r['node'] === $targetNode)
            );
            
            // Get database VMIDs for this node (or globally if unique)
            // Assuming VMIDs are unique across cluster usually, or checks node.
            $dbVmids = Server::where('node_id', $node->id)->pluck('vmid')->map(fn($id) => (int)$id)->all();
            
            $unmanaged = [];
            foreach ($nodeVms as $vm) {
                if (!in_array((int)$vm['vmid'], $dbVmids)) {
                     // Get config for details? Optional, might be slow. Just return list for now.
                     // Basic info is in resource list (name, vmid, status, maxcpu, maxmem, maxdisk)
                     $unmanaged[] = [
                         'vmid' => (int)$vm['vmid'],
                         'name' => $vm['name'] ?? 'Unknown',
                         'status' => $vm['status'],
                         'cpu' => $vm['maxcpu'] ?? 0,
                         'memory' => isset($vm['maxmem']) ? round($vm['maxmem'] / 1024 / 1024 / 1024, 2) : 0, // GB
                         'disk' => isset($vm['maxdisk']) ? round($vm['maxdisk'] / 1024 / 1024 / 1024, 2) : 0, // GB
                     ];
                }
            }
            
            return response()->json(['data' => array_values($unmanaged)]);

        } catch (\Exception $e) {
             return response()->json(['message' => 'Failed to fetch unmanaged VMs', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new server (including Adoption).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_adoption' => ['sometimes', 'boolean'],
            'user_id' => ['required', 'exists:users,id'],
            'node_id' => ['required', 'exists:nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'], // Optional for adoption
            'description' => ['nullable', 'string'],
            'cpu' => ['required', 'integer', 'min:1', 'max:128'],
            'memory' => ['required', 'integer', 'min:1'], // Bytes handled below, validation min 1 (GB/MB?) Controller expects bytes eventually? 
            // Controller validation at line 72 says: min:536870912 (512MB).
            // Frontend sends bytes.
            'disk' => ['required', 'integer', 'min:1'], 
            'bandwidth_limit' => ['nullable', 'integer', 'min:0'],
            'template_vmid' => ['required_without:is_adoption', 'nullable', 'string'], 
            'vmid' => ['nullable', 'integer', 'min:100'], // Required if adoption
            'address_pool_id' => ['nullable', 'exists:address_pools,id'],
            'ip_address' => ['nullable', 'ip'],
        ]);
        
        $isAdoption = $request->boolean('is_adoption');
        
        if ($isAdoption) {
            if (empty($validated['vmid'])) {
                 return response()->json(['message' => 'VMID is required for adoption.'], 422);
            }
            // Verify VM exists and not managed
            $exists = Server::where('node_id', $validated['node_id'])->where('vmid', $validated['vmid'])->exists();
            if ($exists) {
                return response()->json(['message' => 'Server with this VMID is already managed.'], 422);
            }
            
            // Check Proxmox existence
            try {
                $node = Node::findOrFail($validated['node_id']);
                $client = new ProxmoxApiClient($node);
                if (!$client->vmidExists($validated['vmid'])) {
                     return response()->json(['message' => 'VMID not found on Proxmox node.'], 422);
                }
            } catch (\Exception $e) {
                 return response()->json(['message' => 'Failed to verify VMID on Proxmox.'], 422);
            }
        }

        // 1. Template Checks (Skip if Adoption)
        if (!$isAdoption) {
            $template = \App\Models\Template::where('vmid', $validated['template_vmid'])->first();
            if ($template) {
                // ... (Validation logic same as before)
                $errors = [];
                if ($template->min_cpu && $validated['cpu'] < $template->min_cpu) {
                    $errors['cpu'] = ["CPU cores must be at least {$template->min_cpu} for this template."];
                }
                if ($template->min_memory && $validated['memory'] < $template->min_memory) {
                    $minMemoryMB = round($template->min_memory / 1024 / 1024);
                    $errors['memory'] = ["Memory must be at least {$minMemoryMB} MB for this template."];
                }
                if ($template->min_disk && $validated['disk'] < $template->min_disk) {
                    $minDiskGB = round($template->min_disk / 1024 / 1024 / 1024);
                    $errors['disk'] = ["Disk must be at least {$minDiskGB} GB for this template."];
                }
                if (!empty($errors)) {
                    return response()->json(['message' => 'Template requirements not met', 'errors' => $errors], 422);
                }
            }
        }

        // 2. IP Allocation
        $address = null;
        // Logic same as before, allowing IP assignment even for adoption if desired
        if (isset($validated['address_pool_id'])) {
            $pool = \App\Models\AddressPool::find($validated['address_pool_id']);
            $address = $pool->availableAddresses()->first();
            if (!$address) {
                return response()->json(['message' => 'No available IP addresses in the selected pool.'], 422);
            }
        } elseif (isset($validated['ip_address'])) {
            $address = \App\Models\Address::where('address', $validated['ip_address'])
                ->whereNull('server_id')
                ->first();
            if (!$address) {
                return response()->json(['message' => 'The selected IP address is unavailable or assigned.'], 422);
            }
        }

        try {
            $node = Node::findOrFail($validated['node_id']);
            
            // 2b. VMID Allocation (Skip if Adoption)
            if ($isAdoption) {
                $vmid = (int) $validated['vmid'];
            } elseif (isset($validated['vmid'])) {
                $vmid = (int) $validated['vmid'];
                 // Validate collision...
            } else {
                 // ... Auto-allocator (existing logic)
                $client = new ProxmoxApiClient($node);
                $clusterResources = $client->getClusterResources();
                $pveVmids = [];
                foreach ($clusterResources as $resource) {
                    if (isset($resource['vmid'])) {
                        $pveVmids[] = (int) $resource['vmid'];
                    }
                }
                $dbVmids = Server::pluck('vmid')->map(fn($id) => (int) $id)->all();
                $takenVmids = array_unique(array_merge($pveVmids, $dbVmids));

                $vmid = 100;
                $maxAttempts = 999900;
                while (in_array($vmid, $takenVmids)) {
                    $vmid++;
                    if ($vmid > $maxAttempts) throw new \Exception('No available VMID found.');
                }
            }

            // 3. Create Server Record
            $server = Server::create([
                'user_id' => $validated['user_id'],
                'node_id' => $validated['node_id'],
                'vmid' => (string) $vmid,
                'name' => $validated['name'],
                'hostname' => $validated['hostname'],
                'password' => $validated['password'] ?? 'imported', // Placeholder if adoption
                'description' => $validated['description'] ?? null,
                'cpu' => $validated['cpu'],
                'memory' => $validated['memory'],
                'disk' => $validated['disk'],
                'bandwidth_limit' => $validated['bandwidth_limit'],
                'status' => $isAdoption ? 'running' : 'installing', // Default to running if adoption (or check PVE?)
                'is_installing' => $isAdoption ? false : true,
            ]);

            // 4. Assign IP
            if ($address) {
                $address->update(['server_id' => $server->id, 'is_primary' => true]);
            }

            // 5. Dispatch Job (Skip if Adoption)
            if (!$isAdoption) {
                \App\Jobs\Server\CreateServerJob::dispatch(
                    $server,
                    (int) $validated['template_vmid'],
                    $validated['password']
                );
            } else {
                // Update status from real PVE state immediately
                try {
                     $client = new ProxmoxApiClient($node);
                     $status = $client->getVMStatus($vmid);
                     $server->update(['status' => $status['status']]);
                } catch (\Exception $e) {}
            }

            return response()->json([
                'message' => $isAdoption ? 'Server adopted successfully.' : 'Server creation initiated successfully.',
                'data' => $this->formatServer($server),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to initiate server creation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a server.
     */
    public function update(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cpu' => ['sometimes', 'integer', 'min:1', 'max:128'],
            'memory' => ['sometimes', 'integer', 'min:536870912'],
            'disk' => ['sometimes', 'integer', 'min:1073741824'],
            'bandwidth_limit' => ['nullable', 'integer', 'min:0'],
            'is_suspended' => ['sometimes', 'boolean'],
        ]);

        $server->update($validated);

        // If resources changed, update Proxmox config
        if (isset($validated['cpu']) || isset($validated['memory'])) {
            try {
                $client = new ProxmoxApiClient($server->node);
                $config = [];

                if (isset($validated['cpu'])) {
                    $config['cores'] = $validated['cpu'];
                }
                if (isset($validated['memory'])) {
                    $config['memory'] = (int) ($validated['memory'] / 1024 / 1024); // Convert to MB
                }

                if (! empty($config)) {
                    $client->updateVMConfig((int) $server->vmid, $config);
                }
            } catch (ProxmoxApiException $e) {
                // Log error but don't fail the update
                logger()->error('Failed to update Proxmox config', [
                    'server' => $server->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Server updated successfully',
            'data' => $this->formatServer($server->fresh(['user', 'node.location'])),
        ]);
    }

    /**
     * Delete a server.
     */
    public function destroy(Request $request, Server $server): JsonResponse
    {
        $purge = $request->boolean('purge', true);

        try {
            if ($purge) {
                try {
                    $client = new ProxmoxApiClient($server->node);

                    // Stop the VM first if running
                    if ($server->status === 'running') {
                        try {
                            $client->stopVM((int) $server->vmid);
                            sleep(2); // Wait a bit
                        } catch (\Exception $e) {
                            // Ignore stop error
                        }
                    }

                    // Delete from Proxmox
                    $client->deleteVM((int) $server->vmid);

                } catch (\Exception $e) { // Catch broadly to inspect message
                    // Check if error is because VM doesn't exist
                    if (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'No such file or directory')) {
                        // Log and proceed to delete from DB
                         logger()->warning("VM removal skipped: VMID {$server->vmid} not found on Proxmox.");
                    } else {
                        // Re-throw if it's a real error and we wanted to purge
                        throw $e; 
                    }
                }
            }

            // Always release IPs before deleting server record
            $server->addresses()->update([
                'server_id' => null,
                'is_primary' => false
            ]);

            // Delete from database
            $server->delete();

            return response()->json([
                'message' => 'Server deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete server',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Power action on a server.
     */
    public function power(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:start,stop,restart,shutdown,reset'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $action = $request->action;

            match ($action) {
                'start' => $client->startVM((int) $server->vmid),
                'stop' => $client->stopVM((int) $server->vmid),
                'shutdown' => $client->shutdownVM((int) $server->vmid),
                'restart' => $client->rebootVM((int) $server->vmid),
                'reset' => $client->resetVM((int) $server->vmid),
            };

            // Update status
            $newStatus = match ($action) {
                'start', 'restart', 'reset' => 'running',
                'stop', 'shutdown' => 'stopped',
            };
            $server->update(['status' => $newStatus]);

            return response()->json([
                'message' => "Server {$action} initiated",
                'data' => ['status' => $newStatus],
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => "Failed to {$request->action} server",
                'error' => $e->getMessage(),
            ], 422);
        }
    }



    /**
     * Get installation/rebuild progress.
     */
    public function installProgress(Server $server): JsonResponse
    {
        $cachedStep = Cache::get("server_rebuild_step_{$server->id}");

        Log::info("InstallProgress Server {$server->id}: CachedStep=[{$cachedStep}], TaskID=[{$server->installation_task}], Status=[{$server->status}]");

        if (! $server->is_installing && $server->status !== 'rebuilding') {
            if ($cachedStep) {
                Cache::forget("server_rebuild_step_{$server->id}");
            }
            return response()->json([
                'progress' => 100,
                'status' => 'completed',
                'step' => 'completed',
            ]);
        }

        if (! $cachedStep && ! $server->installation_task) {
            return response()->json([
                'progress' => 0,
                'status' => 'pending',
                'step' => 'preparing',
                'stepLabel' => 'Preparing server...',
                'pveTaskType' => null,
            ]);
        }

        $step = RebuildStep::tryFrom($cachedStep ?? 'preparing');

        $response = [
            'progress' => 0,
            'status' => 'running',
            'step' => $cachedStep ?? 'preparing',
            'stepLabel' => $step ? $step->label() : 'Preparing...',
            'hasProgress' => $step ? $step->hasProgress() : false,
        ];

        try {
            if ($step === RebuildStep::INSTALLING_OS && $server->installation_task) {
                $client = new ProxmoxApiClient($server->node);
                $status = $client->getTaskStatus($server->installation_task);

                Log::info("PVE Task Status: " . json_encode($status));

                if ($status['status'] === 'stopped') {
                    if (($status['exitstatus'] ?? 'OK') === 'OK') {
                        $response['progress'] = 75;
                        $response['status'] = 'running';
                    } else {
                        return response()->json([
                            'progress' => 0,
                            'status' => 'failed',
                            'step' => 'installing_os',
                            'stepLabel' => 'Installing OS (clone from template)',
                            'pveTaskType' => 'qmclone',
                            'error' => $status['exitstatus'] ?? 'Unknown error',
                        ]);
                    }
                } else {
                    $log = $client->getTaskLog($server->installation_task);
                    $cloneProgress = 0;
                    foreach (array_reverse($log) as $line) {
                        if (preg_match('/(?:^|\s|\()(\d+(?:\.\d+)?)%\)?/', $line['t'], $matches)) {
                            $cloneProgress = (float) $matches[1];
                            break;
                        }
                    }
                    $response['progress'] = 20 + ($cloneProgress * 0.55);
                    $response['cloneProgress'] = $cloneProgress;
                }
            } elseif ($step) {
                $response['progress'] = $step->progressPercentage();

                if ($step === RebuildStep::CONFIGURING_RESOURCES) {
                    $response['subOperations'] = $step->subOperations();
                }
            } else if ($server->installation_task) {
                $client = new ProxmoxApiClient($server->node);
                $status = $client->getTaskStatus($server->installation_task);
                if ($status['status'] === 'running') {
                    $response['step'] = 'installing_os';
                    $response['stepLabel'] = RebuildStep::INSTALLING_OS->label();
                    $response['pveTaskType'] = 'qmclone';
                    $response['hasProgress'] = true;
                    Cache::put("server_rebuild_step_{$server->id}", 'installing_os', 1200);
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error("InstallProgress Error: " . $e->getMessage());
            return response()->json([
                'progress' => 0,
                'status' => 'unknown',
                'step' => $cachedStep ?? 'preparing',
                'stepLabel' => $step ? $step->label() : 'Preparing...',
                'pveTaskType' => $step ? $step->pveTaskType() : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get server status from Proxmox.
     */
    public function status(Server $server): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($server->node);
            $status = $client->getVMStatus((int) $server->vmid);

            // Update local status
            $proxmoxStatus = $status['status'] ?? 'unknown';
            if ($proxmoxStatus !== $server->status && in_array($proxmoxStatus, ['running', 'stopped'])) {
                $server->update(['status' => $proxmoxStatus]);
            }

            // Fetch guest agent info
            $guestAgent = ['running' => false];
            try {
                $agentInfo = $client->get("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/agent/info");
                $guestAgent = [
                    'running' => true,
                    'version' => $agentInfo['version'] ?? null,
                ];
            } catch (\Exception $e) {
                // Agent not running - not an error
            }

            $vmStatus = $client->get("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/status/current");
            
            $stats = [
                'cpu' => $vmStatus['cpu'] ?? 0,
                'maxcpu' => $vmStatus['maxcpu'] ?? $server->cpu,
                'mem' => $vmStatus['mem'] ?? 0,
                'maxmem' => $vmStatus['maxmem'] ?? $server->memory,
                'disk' => $vmStatus['disk'] ?? 0,
                'maxdisk' => $vmStatus['maxdisk'] ?? $server->disk,
                'netin' => $vmStatus['netin'] ?? 0,
                'netout' => $vmStatus['netout'] ?? 0,
                'status' => $vmStatus['status'] ?? 'unknown',
                'uptime' => $vmStatus['uptime'] ?? 0,
                'agent' => isset($vmStatus['agent']) && $vmStatus['agent'] == 1,
            ];

            // Try to get QEMU Guest Agent data for more accurate disk/network info
            if ($stats['agent']) {
                try {
                    // Get file system info from guest agent
                    $fsInfo = $client->get("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/agent/get-fsinfo");
                    if (isset($fsInfo['result']) && is_array($fsInfo['result'])) {
                        $totalUsed = 0;
                        $totalSize = 0;
                        foreach ($fsInfo['result'] as $fs) {
                            if (isset($fs['used-bytes']) && isset($fs['total-bytes'])) {
                                $totalUsed += $fs['used-bytes'];
                                $totalSize += $fs['total-bytes'];
                            }
                        }
                        if ($totalSize > 0) {
                            $stats['disk'] = $totalUsed;
                            $stats['maxdisk'] = $totalSize;
                        }
                    }
                } catch (\Exception $e) {
                    // Guest agent might not support fsinfo, use fallback data
                    \Log::debug("Failed to fetch QEMU guest agent fsinfo: " . $e->getMessage());
                }

                // Get network interface stats from guest agent
                try {
                    $netInfo = $client->get("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/agent/network-get-interfaces");
                    if (isset($netInfo['result']) && is_array($netInfo['result'])) {
                        $totalRx = 0;
                        $totalTx = 0;
                        foreach ($netInfo['result'] as $iface) {
                            if (isset($iface['statistics'])) {
                                $totalRx += $iface['statistics']['rx-bytes'] ?? 0;
                                $totalTx += $iface['statistics']['tx-bytes'] ?? 0;
                            }
                        }
                        $stats['netin'] = $totalRx;
                        $stats['netout'] = $totalTx;
                    }
                } catch (\Exception $e) {
                    // Guest agent might not support network stats, use fallback
                    \Log::debug("Failed to fetch QEMU guest agent network info: " . $e->getMessage());
                }
            }

            return response()->json([
                'data' => $stats,
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to fetch server status',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List snapshots for a server.
     */
    public function snapshots(Server $server): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);

            $snapshots = $repository->list();
            
            // Filter out 'current'
            $snapshots = array_filter($snapshots, fn ($s) => ($s['name'] ?? '') !== 'current');

            return response()->json([
                'data' => array_values($snapshots),
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to list snapshots',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create a snapshot.
     */
    public function createSnapshot(Request $request, Server $server): JsonResponse
    {
        if ($server->is_suspended) {
             return response()->json(['message' => 'Cannot create snapshot of suspended server'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'include_ram' => ['nullable', 'boolean'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);

            $upid = $repository->create(
                $request->name,
                $request->description,
                $request->boolean('include_ram', false)
            );

            return response()->json([
                'message' => 'Snapshot creation started',
                'upid' => $upid,
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to create snapshot',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Rollback snapshot.
     */
    public function rollbackSnapshot(Request $request, Server $server, string $name): JsonResponse
    {
         if ($server->is_suspended) {
             return response()->json(['message' => 'Cannot rollback suspended server'], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);

            $upid = $repository->rollback($name);

            return response()->json([
                'message' => 'Snapshot rollback started',
                'upid' => $upid,
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to rollback snapshot',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete snapshot.
     */
    public function deleteSnapshot(Server $server, string $name): JsonResponse
    {
        if ($server->is_suspended) {
             return response()->json(['message' => 'Cannot delete snapshot of suspended server'], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxSnapshotRepository($client))
                ->setServer($server);

            $upid = $repository->delete($name);

            return response()->json([
                'message' => 'Snapshot deletion started',
                'upid' => $upid,
            ]);
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to delete server',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Rebuild server with new template.
     */
    public function rebuild(Request $request, Server $server): JsonResponse
    {
        // Check if server is already rebuilding
        if ($server->status === 'rebuilding' || $server->is_installing) {
            return response()->json([
                'message' => 'Server is already being rebuilt or installed',
                'errors' => [
                    'server' => ['Cannot rebuild while server is in use'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'template_vmid' => ['required', 'exists:templates,vmid'],
            'password' => ['nullable', 'string', 'min:8'],
            'name' => ['nullable', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate template exists and get its node
        $template = \App\Models\Template::where('vmid', $validated['template_vmid'])->first();
        if (!$template) {
            return response()->json([
                'message' => 'Template not found',
                'errors' => [
                    'template_vmid' => ['Template does not exist'],
                ],
            ], 404);
        }

        // Note: Cross-node rebuilds are NOT supported by this validation
        // Proxmox does not support cloning templates across different nodes directly
        // Remove this validation if you want to attempt cross-node rebuilds (will fail at Proxmox level)
        if ($template->node_id !== $server->node_id) {
            $templateNode = \App\Models\Node::find($template->node_id);
            $serverNode = \App\Models\Node::find($server->node_id);

            return response()->json([
                'message' => 'Template must be on same Proxmox node as server',
                'errors' => [
                    'template_vmid' => [
                        "Template '{$template->name}' (VMID: {$template->vmid}) is on node '{$templateNode?->cluster}'",
                        "Server is on node '{$serverNode?->cluster}'",
                        'Proxmox does not support cloning across different nodes',
                        'Please select a template from the same node',
                    ],
                ],
            ], 422);
        }

        // Update server details if provided
        $updateData = [];
        if (!empty($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (!empty($validated['hostname'])) {
            $updateData['hostname'] = $validated['hostname'];
        }

        if (!empty($updateData)) {
            $server->update($updateData);
        }

        \Log::info("API: Rebuild request received for server {$server->id} template {$validated['template_vmid']}");

        // Dispatch rebuild job
        \App\Jobs\Server\RebuildServerJob::dispatch(
            $server,
            (int) $validated['template_vmid'],
            $validated['password'] ?? null
        );

        return response()->json([
            'message' => 'Server rebuild initiated',
            'data' => ['status' => 'rebuilding'],
        ]);
    }

    /**
     * Update server resources (CPU, Memory, Disk, Bandwidth).
     */
    public function updateResources(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'cpu' => ['sometimes', 'integer', 'min:1', 'max:128'],
            'memory' => ['sometimes', 'integer', 'min:536870912'], // 512MB min
            'disk' => ['sometimes', 'integer', 'min:' . $server->disk], // Can only upgrade disk
            'bandwidth_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $updateConfig = [];

            // Update Proxmox VM config
            if (isset($validated['cpu'])) {
                $updateConfig['cores'] = $validated['cpu'];
            }
            if (isset($validated['memory'])) {
                $updateConfig['memory'] = (int) ($validated['memory'] / 1024 / 1024); // bytes to MB
            }

            if (!empty($updateConfig)) {
                $client->updateVMConfig((int) $server->vmid, $updateConfig);
            }


            // Handle disk resize (Proxmox only allows increases)
            if (isset($validated['disk']) && $validated['disk'] > $server->disk) {
                $increaseGB = (int) ceil(($validated['disk'] - $server->disk) / 1024 / 1024 / 1024);
                // Proxmox expects "+XG" format for relative increase
                $client->put("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/resize", [
                    'disk' => 'scsi0',
                    'size' => "+{$increaseGB}G"
                ]);
            }

            // Update database
            $server->update(array_intersect_key($validated, array_flip(['cpu', 'memory', 'disk', 'bandwidth_limit'])));

            return response()->json([
                'message' => 'Resources updated successfully',
                'data' => $this->formatServer($server->fresh(), true),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update resources',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get RRD (historical) data for usage graphs.
     */
    public function rrdData(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'timeframe' => ['sometimes', 'string', 'in:hour,day,week,month,year'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $timeframe = $validated['timeframe'] ?? 'hour';
            
            // Proxmox returns: {data: [{time: ..., cpu: ..., mem: ...}, ...]}
            // Pass timeframe as query parameter array (not in URL string)
            $response = $client->get(
                "/nodes/{$server->node->cluster}/qemu/{$server->vmid}/rrddata",
                ['timeframe' => $timeframe]
            );
            
            // Extract the data array from Proxmox response
            $dataPoints = $response['data'] ?? $response;

            return response()->json([
                'data' => $dataPoints,
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to fetch usage data',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reset server password.
     */
    public function resetPassword(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 
                          'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            
            // Detect OS type
            $isWindows = stripos($server->name, 'windows') !== false || 
                        stripos($server->name, 'win') !== false;
            $ciuser = $isWindows ? 'Administrator' : 'root';
            
            // Update Cloud-Init password
            $client->updateVMConfig((int) $server->vmid, [
                'ciuser' => $ciuser,
                'cipassword' => $validated['password'],
            ]);

            return response()->json([
                'message' => 'Password reset successfully. Reboot to apply changes.',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to reset password',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mount ISO.
     */
    public function mountIso(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'storage' => ['required', 'string'],
            'iso' => ['required', 'string'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxConfigRepository($client))
                ->setServer($server);

            $repository->mountIso($request->storage, $request->iso);

            return response()->json(['message' => 'ISO mounted successfully']);
        } catch (ProxmoxApiException $e) {
             return response()->json(['message' => 'Failed to mount ISO', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Unmount ISO.
     */
    public function unmountIso(Request $request, Server $server): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($server->node);
            $repository = (new \App\Repositories\Proxmox\Server\ProxmoxConfigRepository($client))
                ->setServer($server);

            $repository->unmountIso();

            return response()->json(['message' => 'ISO unmounted successfully']);
        } catch (ProxmoxApiException $e) {
             return response()->json(['message' => 'Failed to unmount ISO', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Format server for API response.
     */
    protected function formatServer(Server $server, bool $detailed = false): array
    {
        $data = [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'vmid' => $server->vmid,
            'name' => $server->name,
            'hostname' => $server->hostname,
            'status' => $server->status,
            'is_suspended' => $server->is_suspended,
            'is_installing' => $server->is_installing, // Ensure this is sent
            'installation_task' => $server->installation_task,
            'cpu' => $server->cpu,
            'memory' => $server->memory,
            'memory_formatted' => $server->formatted_memory,
            'disk' => $server->disk,
            'disk_formatted' => $server->formatted_disk,
            'bandwidth_limit' => $server->bandwidth_limit,
            'bandwidth_usage' => $server->bandwidth_usage,
            'user' => $server->user ? [
                'id' => $server->user->id,
                'name' => $server->user->name,
                'email' => $server->user->email,
            ] : null,
            'node_id' => $server->node_id,
            'node' => $server->node ? [
                'id' => $server->node->id,
                'name' => $server->node->name,
                'location' => $server->node->location ? [
                    'id' => $server->node->location->id,
                    'name' => $server->node->location->name,
                    'short_code' => $server->node->location->short_code,
                ] : null,
            ] : null,
            'created_at' => $server->created_at,
            'addresses' => $server->addresses->map(fn ($addr) => [
                'id' => $addr->id,
                'address' => $addr->address,
                'cidr' => $addr->cidr,
                'gateway' => $addr->gateway,
                'type' => $addr->type,
                'is_primary' => $addr->is_primary,
            ]),
        ];

        if ($detailed) {
            $data['description'] = $server->description;
            $data['installed_at'] = $server->installed_at;
            $data['addresses'] = $server->addresses->map(fn ($addr) => [
                'id' => $addr->id,
                'address' => $addr->address,
                'cidr' => $addr->cidr,
                'gateway' => $addr->gateway,
                'type' => $addr->type,
                'is_primary' => $addr->is_primary,
            ]);
        }

        return $data;
    }

    /**
     * Get available IPs from server's node address pools.
     */
    public function availableIPs(Server $server): JsonResponse
    {
        // Get address pools assigned to this server's node
        $addressPools = $server->node->addressPools()->get();
        
        if ($addressPools->isEmpty()) {
            return response()->json([
                'data' => [],
                'message' => 'No address pools assigned to this node',
            ]);
        }
        
        // Get all unassigned addresses from these pools
        $availableAddresses = \App\Models\Address::whereIn('address_pool_id', $addressPools->pluck('id'))
            ->whereNull('server_id')
            ->get()
            ->sort(function ($a, $b) {
                return ip2long($a->address) <=> ip2long($b->address);
            })
            ->values()
            ->map(fn($addr) => [
                'id' => $addr->id,
                'address' => $addr->address,
                'cidr' => $addr->cidr,
                'gateway' => $addr->gateway,
                'type' => $addr->type,
            ]);
        
        return response()->json([
            'data' => $availableAddresses,
            'total' => $availableAddresses->count(),
        ]);
    }

    /**
     * Assign IP to server.
     */
    public function assignIP(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'exists:addresses,id'],
        ]);
        
        $address = \App\Models\Address::findOrFail($validated['address_id']);
        
        // Verify IP is unassigned
        if ($address->server_id) {
            return response()->json([
                'message' => 'IP address already assigned to another server'
            ], 422);
        }
        
        // Verify IP is from this node's pools
        $nodePoolIds = $server->node->addressPools()->pluck('address_pools.id');
        if (!$nodePoolIds->contains($address->address_pool_id)) {
            return response()->json([
                'message' => 'IP address not available for this node'
            ], 422);
        }
        
        // Assign IP
        $address->update([
            'server_id' => $server->id,
            'is_primary' => false,
        ]);
        
        return response()->json([
            'message' => 'IP address assigned successfully',
            'data' => $address->fresh(),
        ]);
    }

    /**
     * Remove IP from server.
     */
    public function removeIP(Server $server, \App\Models\Address $address): JsonResponse
    {
        // Verify ownership
        if ($address->server_id !== $server->id) {
            return response()->json([
                'message' => 'IP address not assigned to this server'
            ], 403);
        }
        
        // Prevent removing primary IP
        if ($address->is_primary) {
            return response()->json([
                'message' => 'Cannot remove primary IP address'
            ], 422);
        }
        
        // Free IP back to pool
        $address->update([
            'server_id' => null,
            'is_primary' => false,
        ]);
        
        return response()->json([
            'message' => 'IP address removed successfully',
        ]);
    }

    /**
     * Set an IP as primary for the server.
     */
    public function setPrimaryIP(Server $server, Address $address): JsonResponse
    {
        // Verify ownership
        if ($address->server_id !== $server->id) {
            return response()->json([
                'message' => 'IP address not assigned to this server'
            ], 403);
        }
        
        // Remove primary flag from all other IPs
        $server->addresses()->update(['is_primary' => false]);
        
        // Set this IP as primary
        $address->update(['is_primary' => true]);
        
        return response()->json([
            'message' => 'Primary IP updated successfully. Click "Update Server" to sync to Proxmox.',
        ]);
    }

    /**
     * Update server network configuration in Proxmox.
     */
    public function updateNetwork(Server $server): JsonResponse
    {
        try {
            $client = new ProxmoxApiClient($server->node);
            $this->updateProxmoxNetwork($client, $server);
            
            return response()->json([
                'message' => 'Network configuration updated successfully',
            ]);
            
        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to update network configuration',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update Proxmox network config with all server IPs.
     */
    protected function updateProxmoxNetwork(ProxmoxApiClient $client, Server $server): void
    {
        $addresses = $server->fresh()->addresses;
        
        if ($addresses->isEmpty()) {
            return;
        }
        
        // Sort addresses: primary IP first, then others
        $sortedAddresses = $addresses->sortByDesc('is_primary')->values();
        
        // Build ipconfig for cloud-init
        $ipconfig = [];
        
        foreach ($sortedAddresses as $index => $address) {
            $ipconfig["ipconfig{$index}"] = sprintf(
                'ip=%s/%d,gw=%s',
                $address->address,
                $address->cidr,
                $address->gateway
            );
        }
        
        // Update VM config
        $client->updateVMConfig((int) $server->vmid, $ipconfig);
    }
}
