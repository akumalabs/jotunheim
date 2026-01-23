<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\FirewallRule;
use App\Services\Firewall\IptablesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FirewallController extends Controller
{
    /**
     * List firewall rules for server.
     */
    public function index(Server $server): JsonResponse
    {
        $rules = $server->firewallRules()
            ->orderBy('position')
            ->orderBy('priority')
            ->get();
            
        return response()->json([
            'data' => $rules,
            'firewall_enabled' => $server->firewall_enabled,
        ]);
    }
    
    /**
     * Enable firewall.
     */
    public function enable(Server $server): JsonResponse
    {
        try {
            $service = new IptablesService();
            $service->enable($server);
            
            $server->update(['firewall_enabled' => true]);
            
            return response()->json([
                'message' => 'Firewall enabled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to enable firewall',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * Disable firewall.
     */
    public function disable(Server $server): JsonResponse
    {
        try {
            $service = new IptablesService();
            $service->disable($server);
            
            $server->update(['firewall_enabled' => false]);
            
            return response()->json([
                'message' => 'Firewall disabled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to disable firewall',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * Create firewall rule.
     */
    public function store(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'direction' => ['required', 'in:in,out,both'],
            'action' => ['required', 'in:allow,deny'],
            'protocol' => ['required', 'in:tcp,udp,icmp,all'],
            'source_address' => ['nullable', 'string'],
            'source_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'dest_address' => ['nullable', 'string'],
            'dest_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ip_version' => ['required', 'in:ipv4,ipv6,both'],
            'priority' => ['nullable', 'integer', 'min:500', 'max:520'],
            'enabled' => ['boolean'],
        ]);
        
        // Auto-assign position
        $maxPosition = $server->firewallRules()->max('position') ?? 0;
        $validated['position'] = $maxPosition + 1;
        
        if (!isset($validated['priority'])) {
            $validated['priority'] = 515; // Default middle priority
        }
        
        $rule = $server->firewallRules()->create($validated);
        
        // Apply if firewall is enabled
        if ($server->firewall_enabled) {
            try {
                $service = new IptablesService();
                $client = new \App\Services\Proxmox\ProxmoxApiClient($server->node);
                $service->applyRule($client, $server, $rule);
            } catch (\Exception $e) {
                // Log but don't fail rule creation
                \Log::error('Failed to apply new firewall rule', [
                    'rule' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return response()->json([
            'message' => 'Firewall rule created successfully',
            'data' => $rule,
        ]);
    }
    
    /**
     * Update firewall rule.
     */
    public function update(Request $request, Server $server, FirewallRule $rule): JsonResponse
    {
        // Verify rule belongs to server
        if ($rule->server_id !== $server->id) {
            return response()->json(['message' => 'Firewall rule not found'], 404);
        }
        
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'direction' => ['sometimes', 'in:in,out,both'],
            'action' => ['sometimes', 'in:allow,deny'],
            'protocol' => ['sometimes', 'in:tcp,udp,icmp,all'],
            'source_address' => ['nullable', 'string'],
            'source_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'dest_address' => ['nullable', 'string'],
            'dest_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ip_version' => ['sometimes', 'in:ipv4,ipv6,both'],
            'priority' => ['sometimes', 'integer', 'min:500', 'max:520'],
            'enabled' => ['sometimes', 'boolean'],
        ]);
        
        $rule->update($validated);
        
        // Re-apply firewall if enabled
        if ($server->firewall_enabled) {
            try {
                $service = new IptablesService();
                $service->enable($server); // Re-apply all rules
            } catch (\Exception $e) {
                \Log::error('Failed to re-apply firewall after rule update', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return response()->json([
            'message' => 'Firewall rule updated successfully',
            'data' => $rule->fresh(),
        ]);
    }
    
    /**
     * Delete firewall rule.
     */
    public function destroy(Server $server, FirewallRule $rule): JsonResponse
    {
        if ($rule->server_id !== $server->id) {
            return response()->json(['message' => 'Firewall rule not found'], 404);
        }
        
        $rule->delete();
        
        // Re-apply firewall if enabled
        if ($server->firewall_enabled) {
            try {
                $service = new IptablesService();
                $service->enable($server);
            } catch (\Exception $e) {
                \Log::error('Failed to re-apply firewall after rule deletion');
            }
        }
        
        return response()->json([
            'message' => 'Firewall rule deleted successfully',
        ]);
    }
    
    /**
     * Apply pre-defined ruleset template.
     */
    public function applyRuleset(Request $request, Server $server, string $template): JsonResponse
    {
        $rulesets = [
            'web-server' => [
                ['name' => 'Allow HTTP', 'protocol' => 'tcp', 'dest_port' => 80, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow HTTPS', 'protocol' => 'tcp', 'dest_port' => 443, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow SSH', 'protocol' => 'tcp', 'dest_port' => 22, 'action' => 'allow', 'direction' => 'both'],
            ],
            'ssh-only' => [
                ['name' => 'Allow SSH', 'protocol' => 'tcp', 'dest_port' => 22, 'action' => 'allow', 'direction' => 'both'],
            ],
            'database' => [
                ['name' => 'Allow MySQL', 'protocol' => 'tcp', 'dest_port' => 3306, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow PostgreSQL', 'protocol' => 'tcp', 'dest_port' => 5432, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow SSH', 'protocol' => 'tcp', 'dest_port' => 22, 'action' => 'allow', 'direction' => 'both'],
            ],
            'mail-server' => [
                ['name' => 'Allow SMTP', 'protocol' => 'tcp', 'dest_port' => 25, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow SMTP Submission', 'protocol' => 'tcp', 'dest_port' => 587, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow IMAPS', 'protocol' => 'tcp', 'dest_port' => 993, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow POP3S', 'protocol' => 'tcp', 'dest_port' => 995, 'action' => 'allow', 'direction' => 'both'],
                ['name' => 'Allow SSH', 'protocol' => 'tcp', 'dest_port' => 22, 'action' => 'allow', 'direction' => 'both'],
            ],
        ];
        
        if (!isset($rulesets[$template])) {
            return response()->json(['message' => 'Invalid ruleset template'], 422);
        }
        
        $maxPosition = $server->firewallRules()->max('position') ?? 0;
        
        foreach ($rulesets[$template] as $index => $ruleData) {
            $server->firewallRules()->create(array_merge($ruleData, [
                'position' => $maxPosition + $index + 1,
                'priority' => 515,
                'ip_version' => 'both',
                'enabled' => true,
            ]));
        }
        
        // Apply if firewall enabled
        if ($server->firewall_enabled) {
            try {
                $service = new IptablesService();
                $service->enable($server);
            } catch (\Exception $e) {
                \Log::error('Failed to apply ruleset');
            }
        }
        
        return response()->json([
            'message' => "Ruleset '{$template}' applied successfully",
            'data' => $server->firewallRules,
        ]);
    }
}
