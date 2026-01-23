<?php

namespace App\Services\Servers;

use App\Jobs\Server\BuildServerJob;
use App\Jobs\Server\ConfigureVmJob;
use App\Jobs\Server\WaitUntilVmIsCreatedJob;
use App\Models\Server;
use App\Models\Template;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * ServerBuildService - Orchestrates server creation
 */
class ServerBuildService
{
    /**
     * Create a new server from template.
     */
    public function create(
        int $nodeId,
        int $userId,
        int $templateId,
        string $name,
        int $cpu,
        int $memory,
        int $disk,
        ?string $password = null,
        ?array $addresses = [],
    ): Server {
        $node = \App\Models\Node::findOrFail($nodeId);
        $template = Template::findOrFail($templateId);

        // Get next VMID
        $client = new \App\Services\Proxmox\ProxmoxApiClient($node);
        $vmid = $client->getNextVmid();

        // Create server record
        $server = Server::create([
            'uuid' => (string) Str::uuid(),
            'node_id' => $node->id,
            'user_id' => $userId,
            'name' => $name,
            'vmid' => $vmid,
            'cpu' => $cpu,
            'memory' => $memory,
            'disk' => $disk,
            'status' => 'installing',
            'bandwidth_limit' => 0,
        ]);

        // Dispatch build job chain
        Bus::chain([
            new BuildServerJob($server, $template),
            new WaitUntilVmIsCreatedJob($server),
            new ConfigureVmJob($server, $password, $addresses),
        ])->dispatch();

        return $server;
    }

    /**
     * Clone a VM from template.
     */
    public function clone(Server $server, Template $template): string
    {
        $client = new \App\Services\Proxmox\ProxmoxApiClient($server->node);

        return $client->cloneVM(
            $template->vmid,
            $server->vmid,
            $server->name,
            $server->node->vm_storage
        );
    }
}
