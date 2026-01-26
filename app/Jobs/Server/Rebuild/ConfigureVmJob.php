<?php

namespace App\Jobs\Server\Rebuild;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Servers\VmSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConfigureVmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        protected Server $server,
        protected ?string $password = null,
        protected array $addressIds = [],
        protected array $sshKeyIds = [],
        protected ?Deployment $deployment = null,
        protected ?int $stepId = null,
    ) {}

    public function handle(): void
    {
        if ($this->stepId) {
            $step = DeploymentStep::find($this->stepId);
            $step->start();
        }
        
        Cache::put("server_rebuild_step_{$this->server->id}", 'configuring_vm', 1200);
        
        $client = new ProxmoxApiClient($this->server->node);
        $syncService = new VmSyncService();
        
        Log::info("[Rebuild] Server {$this->server->id}: Configuring VM {$this->server->vmid}");
        
        try {
            $totalSteps = 3;
            $currentStep = 0;
            
            $syncService->handle($this->server, function () use (&$currentStep, &$step, $stepId) {
                $currentStep++;
                
                if ($step && $stepId) {
                    $step->updateProgress(
                        (int) (($currentStep / $totalSteps) * 100),
                        100
                    );
                    
                    Log::info("[Rebuild] Server {$this->server->id}: Config progress - {$currentStep}/{$totalSteps}");
                }
            });
            
            Log::info("[Rebuild] Server {$this->server->id}: VM configured successfully");
            
            if ($this->stepId) {
                $step = DeploymentStep::find($this->stepId);
                $step->complete();
            }
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Failed to configure VM - " . $e->getMessage());
            
            if ($this->stepId) {
                $step = DeploymentStep::find($this->stepId);
                $step->fail($e->getMessage(), 'configure_failed');
            }
            
            throw $e;
        }
    }
}
