<?php

namespace App\Jobs\Server;

use App\Enums\Rebuild\RebuildStep;
use App\Models\Address;
use App\Models\Server;
use App\Models\SshKey;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConfigureVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        protected Server $server,
        protected ?string $password = null,
        protected array $addressIds = [],
        protected array $sshKeyIds = [],
    ) {}

    public function handle(): void
    {
        Log::info("[Rebuild] Server {$this->server->id}: Configuring VM {$this->server->vmid}");
        Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::CONFIGURING_RESOURCES->value, 1200);

        $client = new ProxmoxApiClient($this->server->node);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($this->server);

        try {
            $configRepo->update([
                'cores' => $this->server->cpu,
                'memory' => $this->server->memory / 1048576,
                'name' => $this->server->hostname,
            ]);

            $client->resizeDisk($this->server->vmid, 'scsi0', $this->server->disk);

            if ($this->password) {
                $cloudinitRepo->setPassword($this->password);
            }

            if (! empty($this->addressIds)) {
                $this->configureNetwork($cloudinitRepo);
            }

            if (! empty($this->sshKeyIds)) {
                $this->configureSshKeys($cloudinitRepo);
            }

            $cloudinitRepo->regenerate();

            Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} configuration complete");

        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Configuration failed - " . $e->getMessage());
            throw $e;
        }
    }

    protected function configureNetwork(ProxmoxCloudinitRepository $cloudinitRepo): void
    {
        $addresses = Address::whereIn('id', $this->addressIds)->get();

        foreach ($addresses as $index => $address) {
            $address->update([
                'server_id' => $this->server->id,
                'is_primary' => $index === 0,
            ]);

            if ($index === 0) {
                $cloudinitRepo->setIpConfig(
                    "{$address->address}/{$address->cidr}",
                    $address->gateway,
                    0
                );
            }
        }
    }

    protected function configureSshKeys(ProxmoxCloudinitRepository $cloudinitRepo): void
    {
        $sshKeys = SshKey::whereIn('id', $this->sshKeyIds)
            ->where('user_id', $this->server->user_id)
            ->get();

        $cloudinitRepo->configure([
            'ssh_keys' => [implode("\n", $sshKeys->pluck('public_key')->toArray())],
        ]);
    }
}
