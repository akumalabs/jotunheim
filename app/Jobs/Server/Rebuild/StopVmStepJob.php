<?php

namespace App\Jobs\Server\Rebuild;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Enums\Server\PowerCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StopVmStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 300;
    public int $backoff = 10;

    public function __construct(
        protected Server $server,
        protected ?Deployment $deployment = null,
        protected ?int $stepId = null,
    ) {}

    public function handle(): void
    {
        if ($this->stepId) {
            $step = DeploymentStep::find($this->stepId);
            $step->start();
        }
        
        Cache::put("server_rebuild_step_{$this->server->id}", 'stopping_server', 1200);
        
        $client = new \App\Services\Proxmox\ProxmoxApiClient($this->server->node);
        
        Log::info("[Rebuild] Server {$this->server->id}: Stopping VM {$this->server->vmid}");
        
        try {
            $status = $client->getVMStatus($this->server->vmid);
            
            if ($status['status'] !== 'stopped') {
                $client->stopVM($this->server->vmid);
                
                $maxWait = 120;
                $waited = 0;
                
                while ($waited < $maxWait) {
                    sleep(5);
                    $waited += 5;
                    
                    $status = $client->getVMStatus($this->server->vmid);
                    
                    if ($status['status'] === 'stopped') {
                        Log::info("[Rebuild] Server {$this->server->id}: VM stopped successfully");
                        
                        if ($this->stepId) {
                            $step = DeploymentStep::find($this->stepId);
                            $step->complete();
                        }
                        
                        return;
                    }
                }
                
                Log::warning("[Rebuild] Server {$this->server->id}: VM did not stop after {$maxWait}s, will continue");
            }
            
            if ($this->stepId) {
                $step = DeploymentStep::find($this->stepId);
                $step->complete();
            }
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Failed to stop VM - " . $e->getMessage());
            
            if ($this->stepId) {
                $step = DeploymentStep::find($this->stepId);
                $step->fail($e->getMessage(), 'stop_failed');
            }
            
            throw $e;
        }
    }
}
