<?php

namespace App\Jobs\Server;

use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Jobs\Server\SendPowerCommandJob;
use App\Jobs\Server\Rebuild\WaitUntilVmIsStoppedStepJob;
use App\Jobs\Server\Rebuild\DeleteVmStepJob;
use App\Jobs\Server\Rebuild\WaitUntilVmIsDeletedStepJob;
use App\Jobs\Server\Rebuild\CloneVmStepJob;
use App\Jobs\Server\WaitUntilVmIsCreatedJob;
use App\Jobs\Server\Rebuild\ConfigureVmJob;
use App\Jobs\Server\Rebuild\BootVmStepJob;
use App\Jobs\Server\Rebuild\FinalizeVmStepJob;
use App\Jobs\Server\Rebuild\HandleRebuildFailureJob;
use App\Enums\Server\PowerCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

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
            'installation_task' => null, // Clear stale task ID to prevent premature 100% progress
        ]);

        // Get user's SSH keys for configuration
        $sshKeyIds = $this->server->user->sshKeys()->pluck('id')->toArray();

        $chain = [
            // Use standard SendPowerCommandJob with middleware protection
            new SendPowerCommandJob($this->server, \App\Enums\Server\PowerCommand::STOP),
            new Rebuild\WaitUntilVmIsStoppedStepJob($this->server),
            new Rebuild\DeleteVmStepJob($this->server),
            new Rebuild\WaitUntilVmIsDeletedStepJob($this->server),
            new Rebuild\CloneVmStepJob($this->server, $this->templateVmid),
            new Rebuild\WaitUntilVmIsCreatedJob($this->server),
            new Rebuild\ConfigureVmJob(
                $this->server,
                $this->password,
                $this->server->addresses->pluck('id')->toArray(),
                $sshKeyIds
            ),
            new Rebuild\BootVmStepJob($this->server),
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
