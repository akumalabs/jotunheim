<?php

namespace App\Jobs\Server;

use App\Models\Node;
use App\Services\BandwidthTrackingService;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class TrackBandwidthJob implements ShouldQueue
{
    use Queueable;

    public function middleware(): array
    {
        return [new WithoutOverlapping('bandwidth:node:'.$this->nodeName)];
    }

    protected ProxmoxApiClient $client;

    protected BandwidthTrackingService $trackingService;

    protected string $nodeName;

    public function __construct(
        string $nodeName,
        BandwidthTrackingService $trackingService
    ) {
        $this->nodeName = $nodeName;
        $this->trackingService = $trackingService;
    }

    public function handle(Node $node): void
    {
        $this->client = new ProxmoxApiClient($node);

        try {
            Log::info("Starting bandwidth tracking for node {$node->name}");

            $this->trackingService->trackForNode($node->cluster ?? $node->name);

            Log::info("Bandwidth tracking completed for node {$node->name}");
        } catch (\Exception $e) {
            Log::error("Failed to track bandwidth for node {$node->name}: ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'bandwidth:'.$this->nodeName;
    }
}
