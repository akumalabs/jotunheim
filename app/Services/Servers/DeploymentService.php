<?php

namespace App\Services\Servers;

use App\Enums\Activity\ServerActivity;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Server;
use App\Services\ActivityService;
use Illuminate\Support\Collection;

class DeploymentService
{
    /**
     * Create a new deployment for a server.
     */
    public function create(Server $server, array $steps = []): Deployment
    {
        $deployment = Deployment::create([
            'server_id' => $server->id,
            'status' => 'pending',
        ]);

        // Create deployment steps
        foreach ($steps as $index => $stepName) {
            DeploymentStep::create([
                'deployment_id' => $deployment->id,
                'name' => $stepName,
                'status' => 'pending',
                'order_column' => $index,
            ]);
        }

        return $deployment->fresh('steps');
    }

    /**
     * Get standard build steps.
     */
    public function getBuildSteps(): array
    {
        return [
            'Allocating resources',
            'Cloning template',
            'Configuring VM',
            'Setting up network',
            'Configuring cloud-init',
            'Starting VM',
        ];
    }

    /**
     * Get reinstall steps.
     */
    public function getReinstallSteps(): array
    {
        return [
            'Stopping VM',
            'Removing old disk',
            'Cloning template',
            'Configuring VM',
            'Starting VM',
        ];
    }

    /**
     * Start the next pending step.
     */
    public function startNextStep(Deployment $deployment): ?DeploymentStep
    {
        $step = $deployment->steps()
            ->where('status', 'pending')
            ->orderBy('order_column')
            ->first();

        if ($step) {
            $step->start();
        }

        return $step;
    }

    /**
     * Complete current step and advance.
     */
    public function completeStep(DeploymentStep $step, ?string $output = null): void
    {
        $step->complete($output);

        $deployment = $step->deployment;

        // Check if all steps are complete
        $remaining = $deployment->steps()
            ->whereIn('status', ['pending', 'running'])
            ->count();

        if ($remaining === 0) {
            $allCompleted = $deployment->steps()
                ->where('status', 'failed')
                ->count() === 0;

            if ($allCompleted) {
                $deployment->complete();
                ActivityService::forServer(
                    $deployment->server,
                    ServerActivity::CREATE->value
                );
            }
        }
    }

    /**
     * Fail deployment.
     */
    public function fail(Deployment $deployment, string $error): void
    {
        // Fail current running step
        $runningStep = $deployment->steps()->where('status', 'running')->first();
        if ($runningStep) {
            $runningStep->fail($error);
        }

        // Skip remaining steps
        $deployment->steps()
            ->where('status', 'pending')
            ->update(['status' => 'skipped']);

        $deployment->fail($error);
    }

    /**
     * Get server's deployment history.
     */
    public function getHistory(Server $server): Collection
    {
        return $server->deployments()
            ->with('steps')
            ->orderByDesc('created_at')
            ->get();
    }
}
