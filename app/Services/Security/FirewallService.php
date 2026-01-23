<?php

namespace App\Services\Security;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxFirewallRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

class FirewallService
{
    protected ProxmoxFirewallRepository $firewallRepo;

    protected Server $server;

    public function __construct(ProxmoxApiClient $client, Server $server)
    {
        $this->server = $server;
        $this->firewallRepo = (new ProxmoxFirewallRepository($client))
            ->setNode($server->node)
            ->setServer($server);
    }

    public function getStatus(): array
    {
        $options = $this->firewallRepo->getOptions();
        $rules = $this->firewallRepo->getRules();

        return [
            'enabled' => $options['enable'] ?? false,
            'rules' => array_map(fn ($rule) => [
                'pos' => $rule['pos'] ?? null,
                'type' => $rule['type'] ?? 'in',
                'action' => $rule['action'] ?? 'ACCEPT',
                'protocol' => $rule['proto'] ?? 'tcp',
                'source_port' => $rule['dport'] ?? null,
                'destination_port' => $rule['sport'] ?? null,
                'source' => $rule['ipset'] ?? null,
                'comment' => $rule['comment'] ?? '',
            ], $rules),
        ];
    }

    public function enable(): void
    {
        $this->firewallRepo->enable();
        Log::info("Firewall enabled for server {$this->server->uuid}");
    }

    public function disable(): void
    {
        $this->firewallRepo->disable();
        Log::info("Firewall disabled for server {$this->server->uuid}");
    }

    public function createRule(array $ruleData): array
    {
        $rule = [
            'type' => $ruleData['type'],
            'action' => $ruleData['action'],
            'proto' => $ruleData['protocol'],
        ];

        if (isset($ruleData['destination_port'])) {
            $rule['dport'] = $ruleData['destination_port'];
        }

        if (isset($ruleData['source_port'])) {
            $rule['sport'] = $ruleData['source_port'];
        }

        if (isset($ruleData['source'])) {
            $rule['ipset'] = $ruleData['source'];
        }

        if (isset($ruleData['comment'])) {
            $rule['comment'] = $ruleData['comment'];
        }

        if (isset($ruleData['enabled'])) {
            $rule['enabled'] = $ruleData['enabled'] ? 1 : 0;
        }

        $result = $this->firewallRepo->createRule($rule);

        Log::info("Firewall rule created for server {$this->server->uuid}", ['rule' => $rule]);

        return $result;
    }

    public function updateRule(int $pos, array $ruleData): array
    {
        $rule = [];

        if (isset($ruleData['action'])) {
            $rule['action'] = $ruleData['action'];
        }

        if (isset($ruleData['enabled'])) {
            $rule['enabled'] = $ruleData['enabled'] ? 1 : 0;
        }

        if (isset($ruleData['comment'])) {
            $rule['comment'] = $ruleData['comment'];
        }

        $result = $this->firewallRepo->updateRule($pos, $rule);

        Log::info("Firewall rule {$pos} updated for server {$this->server->uuid}", ['rule' => $rule]);

        return $result;
    }

    public function deleteRule(int $pos): void
    {
        $this->firewallRepo->deleteRule($pos);

        Log::info("Firewall rule {$pos} deleted for server {$this->server->uuid}");
    }

    public function applyTemplate(string $template): void
    {
        $templates = [
            'web' => [
                ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => 80, 'comment' => 'HTTP'],
                ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => 443, 'comment' => 'HTTPS'],
            ],
            'ssh' => [
                ['type' => 'in', 'action' => 'ACCEPT', 'proto' => 'tcp', 'dport' => 22, 'comment' => 'SSH'],
            ],
            'custom' => [],
        ];

        $rules = $templates[$template] ?? [];

        foreach ($rules as $rule) {
            $this->firewallRepo->createRule($rule);
        }

        Log::info("Firewall template {$template} applied for server {$this->server->uuid}");
    }
}
