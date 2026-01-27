<?php

namespace App\Actions\Server;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxCloudinitRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

/**
 * Update server password
 */
class UpdatePasswordAction
{
    public function __construct(
        private ProxmoxCloudinitRepository $cloudinitRepository,
    ) {}

    public function execute(Server $server, string $password): void
    {
        Log::info("Updating password for server {$server->id}");

        try {
            $client = new ProxmoxApiClient($server->node);
            $cloudinitRepo = (new ProxmoxCloudinitRepository($client))->setServer($server);

            $cloudinitRepo->setPassword($password);

            Log::info("Password updated for server {$server->id}");
        } catch (\Exception $e) {
            Log::error("Failed to update password for server {$server->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
