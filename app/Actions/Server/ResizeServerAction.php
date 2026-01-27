<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Resize server resources (CPU, memory, disk)
 */
class ResizeServerAction
{
    public function __construct(
        private ProxmoxConfigRepository $configRepository,
    ) {}

    public function execute(Server $server, array $options): void
    {
        Log::info("Starting resize for server {$server->uuid}");

        try {
            $client = new ProxmoxApiClient($server->node);
            $configRepo = (new ProxmoxConfigRepository($client))->setServer($server);

            $changes = [];

            if (isset($options['cpu']) && $options['cpu'] > 0 && $options['cpu'] <= 32) {
                $cores = $options['cpu'];
                Log::info("Resizing CPU to {$cores} cores");
                $configRepo->setCpu($cores, 1);
                $changes['cpu'] = $options['cpu'];
            }

            if (isset($options['memory']) && $options['memory'] >= 512 && $options['memory'] <= 1024 * 1024) {
                $memory = $options['memory'];
                $memoryMB = $memory / 1048576;
                Log::info("Resizing memory to {$memory} MB ({$memoryMB} MiB)");
                $configRepo->setMemory($memory);
                $changes['memory'] = $options['memory'];
            }

            if (isset($options['disk']) && $options['disk'] >= 10 && $options['disk'] <= 10240) {
                $disk = $options['disk'];
                $diskGB = $disk / 1073741824;
                Log::info("Resizing disk to {$disk} bytes ({$diskGB} GiB)");

                $newDiskSize = ceil($disk / 1073741824);

                $configRepo->resizeDisk('scsi0', $newDiskSize);
                $changes['disk'] = $options['disk'];
            }

            if (empty($changes)) {
                Log::info("No resize changes needed for server {$server->uuid}");

                return;
            }

            $server->update($changes);

            Log::info("Resize completed for server {$server->uuid}", ['changes' => json_encode($changes)]);

        } catch (\Exception $e) {
            Log::error("Failed to resize server {$server->uuid}: " . $e->getMessage());
            throw $e;
        }
    }
}
