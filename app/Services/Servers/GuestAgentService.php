<?php

namespace App\Services\Servers;

use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxGuestAgentRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

class GuestAgentService
{
    protected ProxmoxApiClient $client;

    protected ?Server $server = null;

    public function __construct(ProxmoxApiClient $client, ?Server $server = null)
    {
        $this->client = $client;
        $this->server = $server;
    }

    protected function getRepo(): ProxmoxGuestAgentRepository
    {
        $repo = (new ProxmoxGuestAgentRepository($this->client))
            ->setNode($this->server->node);

        if ($this->server) {
            $repo->setServer($this->server);
        }

        return $repo;
    }

    public function getOsInfo(): array
    {
        $info = $this->getRepo()->getOsInfo();

        Log::info("Retrieved OS info for server {$this->server->uuid}");

        return $info;
    }

    public function getNetworkInterfaces(): array
    {
        $interfaces = $this->getRepo()->getNetworkInterfaces();

        Log::info("Retrieved network interfaces for server {$this->server->uuid}");

        return $interfaces;
    }

    public function getHostname(): string
    {
        $hostname = $this->getRepo()->getHostname();

        Log::info("Retrieved hostname for server {$this->server->uuid}");

        return $hostname;
    }

    public function getInfo(): array
    {
        $serverUuid = $this->server ? $this->server->uuid : 'unknown';

        $info = $this->getRepo()->getInfo();

        Log::info("Retrieved guest agent info for server {$serverUuid}");

        return $info;
    }

    public function executeCommand(string $command, array $args = []): array
    {
        $result = $this->getRepo()->exec($command, $args);

        Log::info("Executed command '{$command}' on server {$this->server->uuid}", [
            'command' => $command,
            'args' => $args,
            'result' => $result,
        ]);

        return $result;
    }

    public function setUserPassword(string $username, string $password): void
    {
        $this->getRepo()->setUserPassword($username, $password);

        Log::info("Set user password for server {$this->server->uuid}");
    }

    public function ping(): bool
    {
        $ping = $this->getRepo()->ping();

        Log::info("Pinged guest agent for server {$this->server->uuid}", [
            'success' => $ping,
        ]);

        return $ping;
    }

    public function shutdown(): array
    {
        $result = $this->getRepo()->shutdown();

        Log::info("Shutdown guest agent for server {$this->server->uuid}");

        return $result;
    }
}
