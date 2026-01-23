<?php

namespace App\Services\Servers;

use App\Enums\Server\DeploymentStatus;
use App\Enums\Server\DeploymentType;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxServerRepository;
use App\Services\Proxmox\ProxmoxApiClient;
use Illuminate\Support\Facades\Log;

/**
 * ServerDeploymentService - Manages server deployments (create, reinstall, delete)
 */
class ServerDeploymentService
{
    protected ProxmoxApiClient $client;

    public function __construct(ProxmoxApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Start a server deployment.
     */
    public function deploy(
        Server $server,
        DeploymentType $type,
        ?string $description = null,
        array $options = []
    ): Deployment {
        Log::info("Starting {$type->value} deployment for server {$server->uuid}");

        $deployment = Deployment::create([
            'server_id' => $server->id,
            'type' => $type->value,
            'description' => $description,
            'status' => DeploymentStatus::PENDING,
        ]);

        Log::info("Created deployment {$deployment->id} with status PENDING");

        return $this->executeDeployment($deployment);
    }

    /**
     * Execute deployment logic (clone, configure, start).
     */
    protected function executeDeployment(Deployment $deployment): Deployment
    {
        $server = $deployment->server;

        try {
            $repo = (new ProxmoxServerRepository($this->client))->setServer($server);

            switch ($deployment->type) {
                case DeploymentType::CREATE:
                    return $this->executeCreateDeployment($deployment, $repo, $server);

                case DeploymentType::REINSTALL:
                    return $this->executeReinstallDeployment($deployment, $repo, $server);

                case DeploymentType::DELETE:
                    return $this->executeDeleteDeployment($deployment, $repo, $server);

                default:
                    throw new \InvalidArgumentException("Unsupported deployment type: {$deployment->type->value}");
            }
        } catch (\Exception $e) {
            Log::error("Deployment failed for server {$server->uuid}: ".$e->getMessage(), [
                'deployment_id' => $deployment->id,
                'type' => $deployment->type->value,
            ]);

            $deployment->update([
                'status' => DeploymentStatus::FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute create deployment.
     */
    protected function executeCreateDeployment(Deployment $deployment, $repo, Server $server): Deployment
    {
        $step = DeploymentStep::create([
            'deployment_id' => $deployment->id,
            'sequence' => 1,
            'status' => 'pending',
            'message' => 'Initializing deployment',
        ]);

        $deployment->update(['current_step_id' => $step->id]);

        try {
            $template = $deployment->description;

            if (! $template) {
                Log::info('No template specified, skipping creation');
                $step->update([
                    'status' => 'completed',
                    'message' => 'No template specified, cannot create from template',
                ]);

                $deployment->update(['status' => DeploymentStatus::FAILED]);

                return $deployment;
            }

            Log::info("Cloning VM from template {$template}");

            $vmid = $repo->getNextVmid();

            $vmName = "vm-{$vmid}";

            $repo->cloneFromTemplate($template, $vmid, $vmName);

            $step->update([
                'status' => 'completed',
                'message' => "VM cloned as {$vmName}",
            ]);

            $deployment->update(['current_step_id' => $this->nextStep($step)]);

            $step = DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'sequence' => $step->sequence + 1,
                'status' => 'pending',
                'message' => 'Configuring network',
            ]);

            $deployment->update(['current_step_id' => $step->id]);

            $this->configureNetwork($server);

            $step->update([
                'status' => 'completed',
                'message' => 'Network configured',
            ]);

            $deployment->update(['current_step_id' => $this->nextStep($step)]);

            $step = DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'sequence' => $step->sequence + 1,
                'status' => 'pending',
                'message' => 'Configuring resources',
            ]);

            $deployment->update(['current_step_id' => $step->id]);

            $this->configureResources($server);

            $step->update([
                'status' => 'completed',
                'message' => 'Resources configured',
            ]);

            $deployment->update(['current_step_id' => $this->nextStep($step)]);

            $step = DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'sequence' => $step->sequence + 1,
                'status' => 'pending',
                'message' => 'Regenerating cloud-init',
            ]);

            $deployment->update(['current_step_id' => $step->id]);

            $repo->regenerateCloudInit();

            $step->update([
                'status' => 'completed',
                'message' => 'Cloud-init regenerated',
            ]);

            $deployment->update(['current_step_id' => $this->nextStep($step)]);

            $step = DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'sequence' => $step->sequence + 1,
                'status' => 'pending',
                'message' => 'Starting VM',
            ]);

            $deployment->update(['current_step_id' => $step->id]);

            $repo->start();

            $step->update([
                'status' => 'running',
                'message' => 'VM starting',
            ]);

            $server->update([
                'status' => 'installing',
            ]);

            $deployment->update(['status' => DeploymentStatus::RUNNING]);

            Log::info("Deployment {$deployment->id} completed, VM {$vmid} started");

            return $deployment;
        } catch (\Exception $e) {
            Log::error("Create deployment failed for server {$server->uuid}: ".$e->getMessage());

            $step->update([
                'status' => 'failed',
                'message' => "Failed: {$e->getMessage()}",
            ]);

            $deployment->update(['status' => DeploymentStatus::FAILED, 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Execute reinstall deployment.
     */
    protected function executeReinstallDeployment(Deployment $deployment, $repo, Server $server): Deployment
    {
        $step = DeploymentStep::create([
            'deployment_id' => $deployment->id,
            'sequence' => 1,
            'status' => 'pending',
            'message' => 'Preparing for reinstall',
        ]);

        $deployment->update(['current_step_id' => $step->id]);

        try {
            if ($deployment->description) {
                Log::info("Reinstalling server {$server->uuid} from template {$deployment->description}");

                $template = \App\Models\Template::findOrFail($deployment->description);

                $this->deleteExistingVm($server, $repo);

                $vmid = $repo->getNextVmid();

                $vmName = "vm-{$vmid}";

                $repo->cloneFromTemplate($template, $vmid, $vmName);

                $step->update([
                    'status' => 'completed',
                    'message' => 'VM cloned from template',
                ]);

                $deployment->update(['current_step_id' => $this->nextStep($step)]);

                $this->configureNetwork($server);

                $step->update([
                    'status' => 'completed',
                    'message' => 'Network configured',
                ]);

                $deployment->update(['current_step_id' => $this->nextStep($step)]);

                $this->configureResources($server);

                $step->update([
                    'status' => 'completed',
                    'message' => 'Resources configured',
                ]);

                $deployment->update(['current_step_id' => $this->nextStep($step)]);

                $step = DeploymentStep::create([
                    'deployment_id' => $deployment->id,
                    'sequence' => $step->sequence + 1,
                    'status' => 'pending',
                    'message' => 'Regenerating cloud-init',
                ]);

                $deployment->update(['current_step_id' => $step->id]);

                $repo->regenerateCloudInit();

                $step->update([
                    'status' => 'completed',
                    'message' => 'Cloud-init regenerated',
                ]);

                $deployment->update(['current_step_id' => $this->nextStep($step)]);

                $step = DeploymentStep::create([
                    'deployment_id' => $deployment->id,
                    'sequence' => $step->sequence + 1,
                    'status' => 'pending',
                    'message' => 'Starting VM',
                ]);

                $deployment->update(['current_step_id' => $step->id]);

                $repo->start();

                $step->update([
                    'status' => 'running',
                    'message' => 'VM starting',
                ]);

                $server->update([
                    'status' => 'installing',
                ]);

                $deployment->update(['status' => DeploymentStatus::RUNNING]);

                Log::info("Reinstall deployment {$deployment->id} completed, VM {$vmid} started");

                return $deployment;
            }
        } catch (\Exception $e) {
            Log::error("Reinstall deployment failed for server {$server->uuid}: ".$e->getMessage());

            $step->update([
                'status' => 'failed',
                'message' => "Failed: {$e->getMessage()}",
            ]);

            $deployment->update(['status' => DeploymentStatus::FAILED, 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Execute delete deployment.
     */
    protected function executeDeleteDeployment(Deployment $deployment, $repo, Server $server): Deployment
    {
        $step = DeploymentStep::create([
            'deployment_id' => $deployment->id,
            'sequence' => 1,
            'status' => 'pending',
            'message' => 'Stopping VM',
        ]);

        $deployment->update(['current_step_id' => $step->id]);

        try {
            $server->update(['status' => 'installing']);

            $repo->stop();

            $step->update([
                'status' => 'completed',
                'message' => 'VM stopped',
            ]);

            $deployment->update(['current_step_id' => $this->nextStep($step)]);

            $step = DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'sequence' => $step->sequence + 1,
                'status' => 'pending',
                'message' => 'Deleting VM',
            ]);

            $deployment->update(['current_step_id' => $step->id]);

            $vmid = $server->vmid;

            $repo->deleteVM($vmid);

            $step->update([
                'status' => 'completed',
                'message' => 'VM deleted',
            ]);

            $deployment->update(['current_step_id' => $this->nextStep($step)]);

            $step = DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'sequence' => $step->sequence + 1,
                'status' => 'pending',
                'message' => 'Removing server record',
            ]);

            $deployment->update(['current_step_id' => $step->id]);

            $server->delete();

            $step->update([
                'status' => 'completed',
                'message' => 'Server record removed',
            ]);

            $deployment->update(['status' => DeploymentStatus::COMPLETED, 'completed_at' => now()]);

            Log::info("Delete deployment {$deployment->id} completed");

            return $deployment;
        } catch (\Exception $e) {
            Log::error("Delete deployment failed for server {$server->uuid}: ".$e->getMessage());

            $step->update([
                'status' => 'failed',
                'message' => "Failed: {$e->getMessage()}",
            ]);

            $deployment->update(['status' => DeploymentStatus::FAILED, 'error_message' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Configure network for a server.
     */
    protected function configureNetwork(Server $server): void
    {
        $primaryAddress = $server->addresses()->where('is_primary', true)->first();

        if ($primaryAddress) {
            $client = new ProxmoxApiClient($server->node);

            $client->put('/nodes/'.$server->node->cluster.'/qemu/'.$server->vmid.'/config', [
                'ipconfig0' => sprintf('ip=%s,gw=%s', $primaryAddress->address, $primaryAddress->gateway ?? ''),
            ]);
        }
    }

    /**
     * Configure CPU, memory for a server.
     */
    protected function configureResources(Server $server): void
    {
        $client = new ProxmoxApiClient($server->node);

        $client->put('/nodes/'.$server->node->cluster.'/qemu/'.$server->vmid.'/config', [
            'cores' => $server->cpu,
            'memory' => $server->memory / 1048576,
        ]);
    }

    /**
     * Get next step sequence number.
     */
    protected function nextStep(DeploymentStep $step): int
    {
        return $step->sequence + 1;
    }

    /**
     * Delete existing VM before reinstall.
     */
    protected function deleteExistingVm(Server $server, $repo): void
    {
        try {
            $vmStatus = $repo->getStatus();

            if ($vmStatus && $vmStatus['status'] !== 'stopped') {
                Log::info("Stopping existing VM {$server->vmid} before reinstall");

                $repo->stop();
                sleep(2);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to stop existing VM: '.$e->getMessage());
        }
    }
}
