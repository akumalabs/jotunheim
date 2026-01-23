<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxFirewallRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirewallController extends Controller
{
    public function __construct(
        private ProxmoxFirewallRepository $firewallRepository
    ) {}

    /**
     * Get firewall status and rules.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot access firewall of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $options = $repo->getOptions();
            $rules = $repo->getRules();
            $ipsets = $repo->getIpsets();

            return response()->json([
                'data' => [
                    'enabled' => $options['enable'] ?? false,
                    'rules' => array_map(fn ($rule) => [
                        'pos' => $rule['pos'] ?? null,
                        'type' => $rule['type'] ?? 'in',
                        'action' => $rule['action'] ?? 'ACCEPT',
                        'proto' => $rule['proto'] ?? 'tcp',
                        'dport' => $rule['dport'] ?? null,
                        'sport' => $rule['sport'] ?? null,
                        'ipset' => $rule['ipset'] ?? null,
                        'comment' => $rule['comment'] ?? '',
                    ], $rules),
                    'ipsets' => $ipsets ?? [],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get firewall rules for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to get firewall rules',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable firewall on a server.
     */
    public function enable(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot enable firewall on a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $repo->enable();

            Log::info("Firewall enabled for server {$server->uuid}");

            return response()->json([
                'message' => 'Firewall enabled successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to enable firewall for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to enable firewall',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable firewall on a server.
     */
    public function disable(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot disable firewall on a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $repo->disable();

            Log::info("Firewall disabled for server {$server->uuid}");

            return response()->json([
                'message' => 'Firewall disabled successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to disable firewall for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to disable firewall',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a firewall rule.
     */
    public function create(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot create firewall rule on a suspended server',
            ], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:in,out'],
            'action' => ['required', 'in:ACCEPT,DROP,REJECT'],
            'protocol' => ['required', 'in:tcp,udp,icmp'],
            'source_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'destination_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'source_ip' => ['nullable', 'ip'],
            'comment' => ['nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $rule = [
                'type' => $validated['type'],
                'action' => $validated['action'],
                'proto' => $validated['protocol'],
                'dport' => $validated['destination_port'],
            ];

            if (isset($validated['source_port'])) {
                $rule['sport'] = $validated['source_port'];
            }

            if (isset($validated['source_ip'])) {
                $rule['ipset'] = $validated['source_ip'];
            }

            if (isset($validated['comment'])) {
                $rule['comment'] = $validated['comment'];
            }

            if (isset($validated['enabled'])) {
                $rule['enabled'] = $validated['enabled'] ? 1 : 0;
            }

            $repo->createRule($rule);

            Log::info("Firewall rule created for server {$server->uuid}", ['rule' => $rule]);

            return response()->json([
                'message' => 'Firewall rule created successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to create firewall rule for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to create firewall rule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a firewall rule.
     */
    public function update(Request $request, string $uuid, int $pos): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot update firewall rule on a suspended server',
            ], 403);
        }

        $validated = $request->validate([
            'action' => ['sometimes', 'in:ACCEPT,DROP,REJECT'],
            'enabled' => ['sometimes', 'boolean'],
            'comment' => ['sometimes', 'string', 'max:255'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $existingRule = $repo->getRule($pos);

            if (! $existingRule) {
                return response()->json([
                    'message' => 'Rule not found',
                ], 404);
            }

            $rule = [];

            if (isset($validated['action'])) {
                $rule['action'] = $validated['action'];
            }

            if (isset($validated['enabled'])) {
                $rule['enabled'] = $validated['enabled'] ? 1 : 0;
            }

            if (isset($validated['comment'])) {
                $rule['comment'] = $validated['comment'];
            }

            $repo->updateRule($pos, $rule);

            Log::info("Firewall rule {$pos} updated for server {$server->uuid}", ['rule' => $rule]);

            return response()->json([
                'message' => 'Firewall rule updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update firewall rule for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to update firewall rule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a firewall rule.
     */
    public function destroy(Request $request, string $uuid, int $pos): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot delete firewall rule on a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $existingRule = $repo->getRule($pos);

            if (! $existingRule) {
                return response()->json([
                    'message' => 'Rule not found',
                ], 404);
            }

            $repo->deleteRule($pos);

            Log::info("Firewall rule {$pos} deleted for server {$server->uuid}");

            return response()->json([
                'message' => 'Firewall rule deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete firewall rule for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to delete firewall rule',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply rule templates.
     */
    public function applyTemplate(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot apply firewall template on a suspended server',
            ], 403);
        }

        $validated = $request->validate([
            'template' => ['required', 'in:web,ssh,custom'],
            'rules' => ['required', 'array'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $repo = (new ProxmoxFirewallRepository($client))
                ->setNode($server->node)
                ->setServer($server);

            $templates = [
                'web' => [
                    ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => 80, 'comment' => 'HTTP'],
                    ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => 443, 'comment' => 'HTTPS'],
                ],
                'ssh' => [
                    ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => 22, 'comment' => 'SSH'],
                ],
                'custom' => $validated['rules'],
            ];

            foreach ($templates[$validated['template']] as $rule) {
                $repo->createRule($rule);
            }

            Log::info("Firewall template {$validated['template']} applied for server {$server->uuid}");

            return response()->json([
                'message' => 'Firewall template applied successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to apply firewall template for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to apply firewall template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
