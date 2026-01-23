<?php

namespace App\Console\Commands;

use App\Jobs\Server\TrackBandwidthJob;
use App\Models\Node;
use Illuminate\Console\Command;

class TrackBandwidthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bandwidth:track';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Track bandwidth usage for all servers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $nodes = Node::where('status', 'active')->get();

        if ($nodes->isEmpty()) {
            $this->info('No active nodes found for bandwidth tracking');

            return 0;
        }

        $this->info("Starting bandwidth tracking for {$nodes->count()} node(s)");

        $count = 0;
        foreach ($nodes as $node) {
            $nodeName = $node->cluster ?? $node->name;

            TrackBandwidthJob::dispatch($node);
            $count++;

            $this->line("Dispatched bandwidth tracking for node: {$nodeName}");
        }

        $this->info("Bandwidth tracking dispatched for {$count} node(s)");

        return $count;
    }
}
