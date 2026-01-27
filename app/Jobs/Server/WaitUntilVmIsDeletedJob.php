<?php

namespace App\Jobs\Server\Rebuild;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WaitUntilVmIsDeletedStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 120;
    public int $timeout = 600;
    public int $backoff = 3;

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
        
        $client = new \App\Services\Proxmox\ProxmoxApiClient($this->server->node);
        
        Log::info("[Rebuild] Server {$this->server->id}: Waiting for VM {$this->server->vmid} deletion");
        
        try {
            $vmExists = $client->vmidExists($this->server->vmid);
            
            if (!$vmExists) {
                Log::info("[Rebuild] Server {$this->server->id}: VM {$this->server->vmid} deleted successfully");
                
                if ($this->stepId) {
                    $step = DeploymentStep::find($this->stepId);
                    $step->complete();
                }
                
                return;
            }
            
            $this->release($this->backoff);
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Failed to check VM deletion - " . $e->getMessage());
            
            if ($this->stepId) {
                $step = DeploymentStep::find($this->stepId);
                $step->fail($e->getMessage(), 'delete_check_failed');
            }
            
            throw $e;
        }
    }
}
