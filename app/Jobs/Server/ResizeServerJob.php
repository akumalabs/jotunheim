<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxConfigRepository;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResizeServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        protected Server $server,
        protected array $options
    ) {}

    public function handle(): void
    {
        $client = new ProxmoxApiClient($this->server->node);
        $configRepo = (new ProxmoxConfigRepository($client))->setServer($this->server);
        $serverRepo = (new ProxmoxServerRepository($client))->setServer($this->server);

        try {
            Log::info("Starting resize for server {$this->server->uuid}");

            $changes = [];

            if (isset($this->options['cpu']) && $this->options['cpu'] > 0 && $this->options['cpu'] <= 32) {
                $cores = $this->options['cpu'];
                Log::info("Resizing CPU to {$cores} cores");

                $configRepo->update(['cores' => $cores]);
                $changes['cpu'] = $this->options['cpu'];
            }

            if (isset($this->options['memory']) && $this->options['memory'] >= 512 && $this->options['memory'] <= 1024 * 1024) {
                $memory = $this->options['memory'];
                $memoryMB = $memory / 1048576;
                Log::info("Resizing memory to {$memory} MB ({$memoryMB} MiB)");

                $configRepo->update(['memory' => $memory]);
                $changes['memory'] = $this->options['memory'];
            }

            if (isset($this->options['disk']) && $this->options['disk'] >= 10 && $this->options['disk'] <= 10240) {
                $disk = $this->options['disk'];
                $diskGB = $disk / 1073741824;
                Log::info("Resizing disk to {$disk} bytes ({$diskGB} GiB)");

                $newDiskSize = ceil($disk / 1073741824);

                $this->resizeDiskWithRetry($client, $serverRepo, $configRepo, $newDiskSize);
                $changes['disk'] = $this->options['disk'];
            }

            if (empty($changes)) {
                Log::info("No resize changes needed for server {$this->server->uuid}");

                return;
            }

            $this->server->update($changes);

            if (isset($changes['disk'])) {
                $this->server->update(['status' => 'running']);
            }

            Log::info("Resize completed for server {$this->server->uuid}", ['changes' => json_encode($changes)]);

        } catch (ProxmoxApiException $e) {
            Log::error("Failed to resize server {$this->server->uuid}: " . $e->getMessage());
            $this->server->update(['status' => 'error']);
            throw $e;
        }
    }

    protected function resizeDiskWithRetry(
        ProxmoxApiClient $client,
        ProxmoxServerRepository $serverRepo,
        ProxmoxConfigRepository $configRepo,
        int $newDiskSize
    ): void {
        $attempts = 0;
        $maxResizeAttempts = 5;
        $backoffs = [10, 20, 40, 60, 60];

        while ($attempts < $maxResizeAttempts) {
            try {
                if (!$serverRepo->waitUntilUnlocked(60, 2)) {
                    throw new \Exception("VM locked timeout before resize attempt {$attempts}");
                }

                $taskUpid = $configRepo->resizeDisk('scsi0', $newDiskSize);

                if (is_string($taskUpid) && str_contains($taskUpid, 'UPID:')) {
                    $client->waitForTask($taskUpid, 600);
                    Log::info("Resize task completed: {$taskUpid}");
                }

                if (!$serverRepo->waitUntilUnlocked(120, 2)) {
                    throw new \Exception("VM locked timeout after resize");
                }

                break;
            } catch (\Exception $e) {
                $attempts++;
                $msg = $e->getMessage();

                if (str_contains($msg, 'smaller than') || str_contains($msg, 'size match')) {
                    Log::info("Resize skipped by PVE (size match).");
                    break;
                }

                if ($attempts >= $maxResizeAttempts) {
                    if (str_contains($msg, 'timeout') || str_contains($msg, 'locked')) {
                        throw $e;
                    }
                    Log::warning("Resize failed but proceeding: " . $msg);
                    break;
                }

                $sleepTime = $backoffs[$attempts - 1] ?? 60;
                Log::warning("Resize attempt {$attempts} failed (Lock/Timeout), retrying in {$sleepTime}s...");
                sleep($sleepTime);
            }
        }
    }
}
