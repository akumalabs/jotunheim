<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Support\Facades\Log;

class ServerResizeService
{
    protected ProxmoxApiClient $client;

    public function __construct(ProxmoxApiClient $client)
    {
        $this->client = $client;
    }

    public function resize(Server $server, array $options): void
    {
        try {
            Log::info("Starting resize for server {$server->uuid}");

            $repo = (new ProxmoxServerRepository($this->client))->setServer($server);

            $changes = [];

            if (isset($options['cpu']) && $options['cpu'] > 0 && $options['cpu'] <= 32) {
                $cores = $options['cpu'];
                Log::info("Resizing CPU to {$cores} cores");
                $repo->update(['cores' => $cores]);
                $changes['cpu'] = $options['cpu'];
            }

            if (isset($options['memory']) && $options['memory'] >= 512 && $options['memory'] <= 1024 * 1024) {
                $memory = $options['memory'];
                $memoryMB = $memory / 1048576;
                Log::info("Resizing memory to {$memory} MB ({$memoryMB} MiB)");
                $repo->update(['memory' => $memory]);
                $changes['memory'] = $options['memory'];
            }

            if (isset($options['disk']) && $options['disk'] >= 10 && $options['disk'] <= 10240) {
                $disk = $options['disk'];
                $diskGB = $disk / 1073741824;
                Log::info("Resizing disk to {$disk} GB ({$diskGB} GiB)");

                $newDiskSize = ceil($disk / 1073741824);
                $repo->resizeDisk('scsi0', "{$newDiskSize}G");
                $changes['disk'] = $options['disk'];
            }

            if (empty($changes)) {
                Log::info("No resize changes needed for server {$server->uuid}");

                return;
            }

            $server->update($changes);

            if (isset($changes['disk'])) {
                $server->update(['status' => 'installing']);
            } else {
                $server->update($changes);
            }

            Log::info("Resize completed for server {$server->uuid}", ['changes' => json_encode($changes)]);

        } catch (ProxmoxApiException $e) {
            Log::error("Failed to resize server {$server->uuid}: ".$e->getMessage());
            throw $e;
        }
    }
}
