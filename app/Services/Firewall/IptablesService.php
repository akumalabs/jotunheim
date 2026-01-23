<?php

namespace App\Services\Firewall;

use App\Models\Server;
use App\Models\FirewallRule;
use App\Services\Proxmox\ProxmoxApiClient;

class IptablesService
{
    /**
     * Enable firewall - apply all enabled rules.
     */
    public function enable(Server $server): void
    {
        $client = new ProxmoxApiClient($server->node);
        
        // First, set default policies to ACCEPT (prevent lockout)
        $this->setDefaultPolicies($client, $server, 'ACCEPT');
        
        // Flush existing rules
        $this->flushRules($client, $server);
        
        // Get all enabled rules ordered by position
        $rules = $server->firewallRules()
            ->where('enabled', true)
            ->orderBy('position')
            ->get();
            
        // Apply each rule
        foreach ($rules as $rule) {
            $this->applyRule($client, $server, $rule);
        }
    }
    
    /**
     * Disable firewall - flush all rules and reset to ACCEPT.
     */
    public function disable(Server $server): void
    {
        $client = new ProxmoxApiClient($server->node);
        
        $this->flushRules($client, $server);
        $this->setDefaultPolicies($client, $server, 'ACCEPT');
    }
    
    /**
     * Apply single firewall rule via iptables.
     */
    public function applyRule(ProxmoxApiClient $client, Server $server, FirewallRule $rule): void
    {
        $commands = [];
        
        // Generate iptables commands based on IP version
        if (in_array($rule->ip_version, ['ipv4', 'both'])) {
            $commands[] = $this->buildIptablesCommand($rule, 'iptables');
        }
        
        if (in_array($rule->ip_version, ['ipv6', 'both'])) {
            $commands[] = $this->buildIptablesCommand($rule, 'ip6tables');
        }
        
        // Execute commands via QEMU guest agent
        foreach ($commands as $cmd) {
            try {
                $client->post("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/agent/exec", [
                    'command' => $cmd
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to apply firewall rule', [
                    'server' => $server->id,
                    'rule' => $rule->id,
                    'command' => $cmd,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }
    
    /**
     * Build iptables command from rule.
     */
    protected function buildIptablesCommand(FirewallRule $rule, string $binary = 'iptables'): string
    {
        $parts = [$binary];
        
        // Determine chain based on direction
        $chains = [];
        if (in_array($rule->direction, ['in', 'both'])) {
            $chains[] = 'INPUT';
        }
        if (in_array($rule->direction, ['out', 'both'])) {
            $chains[] = 'OUTPUT';
        }
        
        $chain = $chains[0]; // For now, use first chain
        $parts[] = "-A {$chain}";
        
        // Protocol
        if ($rule->protocol !== 'all') {
            $parts[] = "-p {$rule->protocol}";
        }
        
        // Source
        if ($rule->source_address && $rule->source_address !== '*') {
            $parts[] = "-s {$rule->source_address}";
        }
        if ($rule->source_port) {
            $parts[] = "--sport {$rule->source_port}";
        }
        
        // Destination
        if ($rule->dest_address && $rule->dest_address !== '*') {
            $parts[] = "-d {$rule->dest_address}";
        }
        if ($rule->dest_port) {
            $parts[] = "--dport {$rule->dest_port}";
        }
        
        // Action (ACCEPT or DROP)
        $action = $rule->action === 'allow' ? 'ACCEPT' : 'DROP';
        $parts[] = "-j {$action}";
        
        return implode(' ', $parts);
    }
    
    /**
     * Flush all iptables rules.
     */
    protected function flushRules(ProxmoxApiClient $client, Server $server): void
    {
        $commands = [
            'iptables -F',      // Flush all rules
            'iptables -X',      // Delete all chains
            'ip6tables -F',
            'ip6tables -X',
        ];
        
        foreach ($commands as $cmd) {
            try {
                $client->post("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/agent/exec", [
                    'command' => $cmd
                ]);
            } catch (\Exception $e) {
                // Continue even if one fails
                \Log::warning('Failed to flush iptables', [
                    'command' => $cmd,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    
    /**
     * Set default policies.
     */
    protected function setDefaultPolicies(ProxmoxApiClient $client, Server $server, string $policy): void
    {
        $commands = [
            "iptables -P INPUT {$policy}",
            "iptables -P FORWARD {$policy}",
            "iptables -P OUTPUT {$policy}",
            "ip6tables -P INPUT {$policy}",
            "ip6tables -P FORWARD {$policy}",
            "ip6tables -P OUTPUT {$policy}",
        ];
        
        foreach ($commands as $cmd) {
            try {
                $client->post("/nodes/{$server->node->cluster}/qemu/{$server->vmid}/agent/exec", [
                    'command' => $cmd
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to set default policy', [
                    'command' => $cmd,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
