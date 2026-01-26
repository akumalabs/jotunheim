<?php

namespace App\Jobs\Server\Rebuild;

use App\Enums\Rebuild\RebuildStep;
use App\Models\Server;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CloneVmStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        protected Server $server,
        protected int $templateVmid,
    ) {}

    public function handle(): void
    {
        Cache::put("server_rebuild_step_{$this->server->id}", RebuildStep::INSTALLING_OS->value, 1200);

        $client = new ProxmoxApiClient($this->server->node);

        Log::info("[Rebuild] Server {$this->server->id}: Cloning template {$this->templateVmid} to VMID {$this->server->vmid}");

        try {
            $vmName = $this->server->name;

            $response = $client->cloneVM($this->templateVmid, $this->server->vmid, [
                'name' => $vmName,
                'full' => 1,
            ]);

            Log::info("[Rebuild] Server {$this->server->id}: Proxmox API response: " . json_encode($response));
            Log::info("[Rebuild] Server {$this->server->id}: Response UPID: " . ($response['data'] ?? 'N/A'));

            if (isset($response['data'])) {
                $upid = $response['data'];
                $this->server->update(['installation_task' => $upid]);
                Log::info("[Rebuild] Server {$this->server->id}: Clone started with UPID {$upid}");
            } elseif (is_string($response)) {
                $this->server->update(['installation_task' => $response]);
                Log::info("[Rebuild] Server {$this->server->id}: Clone started with UPID {$response}");
            } else {
                Log::warning("[Rebuild] Server {$this->server->id}: Unexpected response format from cloneVM: " . json_encode($response));
                // Fallback: try to find the upid in response if it's the raw array
                if (isset($response['data'])) {
                     $this->server->update(['installation_task' => $response['data']]);
                }
            }
        } catch (\Exception $e) {
            Log::error("[Rebuild] Server {$this->server->id}: Clone failed - " . $e->getMessage());
            $this->server->update(['status' => 'failed']);
            throw $e;
        }
    }
}
