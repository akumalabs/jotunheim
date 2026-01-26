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

class WaitUntilVmIsCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 120;
    public int $timeout = 600;
    public int $backoff = 3;

    public function __construct(
        protected Server $server,
        protected ?Deployment $deployment = null,
    ) {}

    public function handle(): void
    {
        $client = new \App\Services\Proxmox\ProxmoxApiClient($this->server->node);
        $parser = new \App\Services\Rebuild\ProxmoxTaskLogParser();
        
        Log::info("[Rebuild] Server {$this->server->id}: Monitoring VM {$this->server->vmid} creation progress");
        
        try {
            // Refresh server to get the latest installation_task set by CloneVmStepJob
            $this->server->refresh();
            $taskId = $this->server->installation_task;
            
            if (!$taskId) {
                Log::warning("[Rebuild] Server {$this->server->id}: No installation task found yet.");
                $this->release($this->backoff);
                return;
            }
            
            $status = $client->getTaskStatus($taskId);
            
            if ($status['status'] === 'stopped') {
                if (($status['exitstatus'] ?? 'OK') === 'OK') {
                    Log::info("[Rebuild] Server {$this->server->id}: Clone task completed successfully");
                    
                    if ($this->deployment) {
                        $cloneStep = $this->deployment->steps()->where('name', 'cloning_template')->first();
                        if ($cloneStep && $cloneStep->status !== 'completed') {
                            $cloneStep->complete();
                        }
                    }
                    
                    return;
                } else {
                    Log::error("[Rebuild] Server {$this->server->id}: Clone task failed - {$status['exitstatus']}");
                    
                    if ($this->deployment) {
                        $cloneStep = $this->deployment->steps()->where('name', 'cloning_template')->first();
                        if ($cloneStep) {
                            $cloneStep->fail($status['exitstatus'] ?? 'Unknown error', 'clone_task_failed');
                        }
                    }
                    
                    throw new \Exception("Clone task failed: {$status['exitstatus']}");
                }
            }
            
            if ($status['status'] === 'running') {
                $logs = $client->getTaskLog($taskId);
                $progressData = $parser->parseCloneProgress($logs);
                
                if ($progressData) {
                    $cloneStep = $this->deployment->steps()->where('name', 'cloning_template')->first();
                    
                    if ($cloneStep) {
                        $cloneStep->updateProgress(
                            (int) $progressData['progress_percent'],
                            100
                        );
                        
                        Log::info("[Rebuild] Server {$this->server->id}: Clone progress - {$progressData['current_formatted']} / {$progressData['total_formatted']} ({$progressData['progress_percent']}%)");
                    }
                }
            }
            
            $this->release($this->backoff);
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Failed to monitor VM creation - " . $e->getMessage());
            
            if ($this->deployment) {
                $cloneStep = $this->deployment->steps()->where('name', 'cloning_template')->first();
                if ($cloneStep) {
                    $cloneStep->fail($e->getMessage(), 'monitor_failed');
                }
            }
            
            throw $e;
        }
    }
}
