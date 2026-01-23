<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        protected Server $server,
        protected int $templateVmid,
        protected ?string $password = null
    ) {
        // Constructor runs on Dispatch (Sync) and Deserialization (Worker)
        Log::info("Job: RebuildServerJob constructed for server {$this->server->id}");
    }

    public function handle(): void
    {
        Log::info("Job: RebuildServerJob handle() started for server {$this->server->id}");
        Log::info("Starting rebuild chain for server {$this->server->id}");

        $this->server->update([
            'status' => 'rebuilding',
            'installation_task' => null // Clear stale task ID to prevent premature 100% progress
        ]);

        // Get user's SSH keys for configuration
        $sshKeyIds = $this->server->user->sshKeys()->pluck('id')->toArray();

        $chain = [
            // Use standard SendPowerCommandJob with middleware protection
            new \App\Jobs\Server\SendPowerCommandJob($this->server, \App\Enums\Server\PowerCommand::STOP),
            new \App\Jobs\Server\Rebuild\WaitUntilVmIsStoppedStepJob($this->server),
            new \App\Jobs\Server\Rebuild\DeleteVmStepJob($this->server),
            new \App\Jobs\Server\Rebuild\WaitUntilVmIsDeletedStepJob($this->server),
            new \App\Jobs\Server\Rebuild\CloneVmStepJob($this->server, $this->templateVmid),
            new \App\Jobs\Server\WaitUntilVmIsCreatedJob($this->server),
            new \App\Jobs\Server\ConfigureVmJob(
                $this->server,
                $this->password,
                $this->server->addresses->pluck('id')->toArray(),
                $sshKeyIds
            ),
            new \App\Jobs\Server\Rebuild\BootVmStepJob($this->server),
            new \App\Jobs\Server\Rebuild\FinalizeVmStepJob($this->server),
            // Add cleanup job to reset status on failure
            new \App\Jobs\Server\Rebuild\HandleRebuildFailureJob($this->server, $this->server->status),
        ];

        \Illuminate\Support\Facades\Bus::chain($chain)
            ->catch(function (\Throwable $e) {
                Log::error("Rebuild chain failed for server {$this->server->id}: " . $e->getMessage());
                $this->server->update([
                    'status' => 'failed',
                    'is_installing' => false,
                    'installation_task' => null,
                ]);
                \Illuminate\Support\Facades\Cache::forget("server_rebuild_step_{$this->server->id}");
            })
            ->dispatch();
    }
}
