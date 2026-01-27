<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\Http\ProxmoxApiException;
use Illuminate\Support\Facades\Log;

/**
 * Build a server on Proxmox
 * Encapsulates server creation logic
 */
class BuildServerAction
{
    public function __construct(
        private ProxmoxConfigRepository $configRepository,
        private ProxmoxPowerRepository $powerRepository,
        private ProxmoxServerRepository $serverRepository,
    ) {}

    /**
     * Execute server build process
     */
    public function execute(Server $server, int $templateVmid, ?string $password = null, array $sshKeys = []): string
    {
        Log::info("Building server {$server->id} (VMID: {$server->vmid})");

        try {
            $taskUpid = $this->cloneServer($server, $templateVmid);

            $this->waitForUnlock($server, 'after clone', 60);

            $this->configureResources($server);

            $this->waitForUnlock($server, 'after resource config', 60);

            $this->resizeDisk($server);

            $this->waitForUnlock($server, 'after resize', 120);

            $this->configureCloudInit($server, $password, $sshKeys);

            $this->waitForUnlock($server, 'before start', 300);

            $this->powerRepository->setServer($server)->start();

            $this->updateServerStatus($server, 'running');

            Log::info("Server {$server->id} build completed", ['task_upid' => $taskUpid]);

            return $taskUpid;
        } catch (ProxmoxApiException $e) {
            Log::error("Failed to build server {$server->id}: " . $e->getMessage());
            throw $e;
        }
    }

    protected function cloneServer(Server $server, int $templateVmid): string
    {
        Log::info("Cloning template {$templateVmid} to VMID {$server->vmid}...");

        $client = new ProxmoxApiClient($server->node);

        $taskUpid = $client->cloneVM(
            $templateVmid,
            (int) $server->vmid,
            [
                'name' => $server->hostname ?? \Illuminate\Support\Str::slug($server->name),
            ]
        );

        Log::info("Clone completed with task: {$taskUpid}");

        return $taskUpid;
    }

    protected function configureResources(Server $server): void
    {
        Log::info("Configuring resources...");

        $client = new ProxmoxApiClient($server->node);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($server);

        $configRepo->update([
            'cores' => $server->cpu,
            'memory' => (int) ($server->memory / 1024 / 1024),
            'description' => "Managed by Midgard Panel | User: {$server->user_id}",
            'onboot' => 1,
        ]);
    }

    protected function resizeDisk(Server $server): void
    {
        Log::info("Resizing disk...");

        $client = new ProxmoxApiClient($server->node);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($server);

        $currentConfig = $configRepo->get();
        $diskString = $currentConfig['scsi0'] ?? $currentConfig['virtio0'] ?? $currentConfig['ide0'] ?? $currentConfig['sata0'] ?? null;

        if ($diskString && preg_match('/size=(\d+(\.\d+)?[TGMK]?)/', $diskString, $matches)) {
            $sizeStr = $matches[1];
            $unit = substr($sizeStr, -1);
            $value = is_numeric($unit) ? (float) $sizeStr : (float) substr($sizeStr, 0, -1);

            $bytes = match(strtoupper($unit)) {
                'T' => $value * 1024 * 1024 * 1024 * 1024,
                'G' => $value * 1024 * 1024 * 1024,
                'M' => $value * 1024 * 1024,
                'K' => $value * 1024,
                default => $value,
            };

            if ($bytes >= ($server->disk - 1048576)) {
                Log::info("Disk already at requested size ({$sizeStr}). Skipping resize.");
                return;
            }
        }

        $configRepo->resizeDisk('scsi0', $server->disk);
    }

    protected function configureCloudInit(Server $server, ?string $password, array $sshKeys): void
    {
        Log::info("Applying Cloud-Init...");

        $client = new ProxmoxApiClient($server->node);
        $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($server);

        $ciConfig = [];
        $ciAttempts = 0;
        $maxCiAttempts = 10;
        $ciBackoff = [5, 10, 15, 20, 30, 40, 50, 60, 60, 60, 60];

        while ($ciAttempts < $maxCiAttempts) {
            try {
                $template = Template::where('vmid', $server->template)->first();
                $isWindows = $template && (
                    stripos($template->name, 'windows') !== false ||
                    stripos($template->name, 'win') !== false
                );

                $ciConfig['user'] = $isWindows ? 'Administrator' : 'root';

                if ($password) {
                    $ciConfig['password'] = $password;
                    Log::info("Password configured for Cloud-Init.");
                } else {
                    Log::warning("No password provided for Cloud-Init.");
                }

                if (!empty($sshKeys) || $server->user) {
                    $ciConfig['ssh_keys'] = array_merge($sshKeys, $server->user->sshKeys()->pluck('public_key')->toArray());
                }

                $address = $server->primaryAddress();
                if ($address) {
                    Log::info("Configuring network: {$address->full_address}");
                    $ciConfig['ip'] = $address->full_address;
                    $ciConfig['gateway'] = $address->gateway;
                } else {
                    Log::warning("No primary address assigned. IP configuration skipped.");
                }

                $cloudinitRepo->configure($ciConfig);
                break;
            } catch (ProxmoxApiException $e) {
                $ciAttempts++;
                $msg = $e->getMessage();

                if ($ciAttempts >= $maxCiAttempts) {
                    throw new \Exception("Cloud-Init config failed after {$maxCiAttempts} attempts: " . $msg, 0, $e);
                }

                if (str_contains($msg, 'lock') || str_contains($msg, 'timeout')) {
                    $sleep = $ciBackoff[$ciAttempts - 1] ?? 60;
                    Log::warning("Cloud-Init config failed (Lock/Timeout), retry {$ciAttempts}/{$maxCiAttempts} in {$sleep}s...");
                    sleep($sleep);
                } else {
                    throw $e;
                }
            }
        }

        $cloudinitRepo->regenerate();
    }

    protected function waitForUnlock(Server $server, string $stage, int $timeout): void
    {
        $serverRepo = (new ProxmoxServerRepository(new ProxmoxApiClient($server->node)))->setServer($server);

        if (!$serverRepo->waitUntilUnlocked($timeout, 2)) {
            throw new \Exception("VM locked timeout {$stage}.");
        }
    }

    protected function updateServerStatus(Server $server, string $status): void
    {
        $server->update([
            'status' => $status,
            'is_installing' => false,
            'installed_at' => $status === 'running' ? now() : null,
        ]);
    }
}
