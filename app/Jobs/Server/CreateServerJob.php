<?php

namespace App\Jobs\Server;

use App\Actions\Server\BuildServerAction;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        protected Server $server,
        protected int $templateVmid,
        protected ?string $password = null,
        protected array $sshKeys = []
    ) {}

    public function handle(BuildServerAction $buildServerAction): void
    {
        logger()->info("Starting creation details for Server: {$this->server->id} (VMID: {$this->server->vmid})");

        try {
            $buildServerAction->execute($this->server, $this->templateVmid, $this->password, $this->sshKeys);

            logger()->info("Server {$this->server->id} creation successful.");

        } catch (\Exception $e) {
            logger()->error("Server creation failed: " . $e->getMessage());

            $this->server->update([
                'status' => 'failed',
                'is_installing' => false,
            ]);

            throw $e;
        }
    }
}
