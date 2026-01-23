<?php

namespace App\Jobs\Node;

use App\Models\Node;
use App\Services\Nodes\ServerUsagesSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncServerUsagesJob - Periodic sync of all server usages on a node
 */
class SyncServerUsagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        protected Node $node,
    ) {}

    public function handle(ServerUsagesSyncService $syncService): void
    {
        logger()->info("Syncing server usages for node {$this->node->name}");

        try {
            $count = $syncService->sync($this->node);
            logger()->info("Synced {$count} servers on node {$this->node->name}");
        } catch (\Exception $e) {
            logger()->error('Failed to sync usages: '.$e->getMessage());
        }
    }
}
