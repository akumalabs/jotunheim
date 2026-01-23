<?php

namespace App\Jobs\Node;

use App\Models\Node;
use App\Repositories\Proxmox\Server\ProxmoxActivityRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * MonitorIsoDownloadJob - Track ISO download progress
 */
class MonitorIsoDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;

    public int $backoff = 10;

    public function __construct(
        protected Node $node,
        protected string $upid,
        protected ?int $isoId = null,
    ) {}

    public function handle(): void
    {
        $client = new ProxmoxApiClient($this->node);
        $activityRepo = (new ProxmoxActivityRepository($client))->setNode($this->node);

        try {
            $status = $activityRepo->getTaskStatus($this->upid);

            if ($status['status'] === 'running') {
                // Still downloading, retry later
                $this->release($this->backoff);

                return;
            }

            if ($status['exitstatus'] === 'OK') {
                logger()->info("ISO download completed for node {$this->node->name}");
                // Could update ISO record status here if tracking
            } else {
                logger()->error('ISO download failed: '.($status['exitstatus'] ?? 'Unknown'));
            }

        } catch (\Exception $e) {
            logger()->error('Failed to check ISO download: '.$e->getMessage());
            $this->release($this->backoff);
        }
    }
}
