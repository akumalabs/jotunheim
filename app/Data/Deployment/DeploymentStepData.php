<?php

namespace App\Data\Deployment;

use App\Models\DeploymentStep;
use Spatie\LaravelData\Data;

class DeploymentStepData extends Data
{
    public function __construct(
        public int $id,
        public int $deployment_id,
        public string $name,
        public string $status,
        public ?string $output,
        public ?string $error,
        public int $order_column,
        public ?string $started_at,
        public ?string $completed_at,
        public ?string $duration,
    ) {}

    public static function fromModel(DeploymentStep $step): self
    {
        $duration = null;
        if ($step->started_at && $step->completed_at) {
            $duration = $step->started_at->diffForHumans($step->completed_at, true);
        }

        return new self(
            id: $step->id,
            deployment_id: $step->deployment_id,
            name: $step->name,
            status: $step->status,
            output: $step->output,
            error: $step->error,
            order_column: $step->order_column,
            started_at: $step->started_at?->toIso8601String(),
            completed_at: $step->completed_at?->toIso8601String(),
            duration: $duration,
        );
    }

    /**
     * Get status icon.
     */
    public function getIcon(): string
    {
        return match ($this->status) {
            'pending' => 'clock',
            'running' => 'spinner',
            'completed' => 'check-circle',
            'failed' => 'x-circle',
            'skipped' => 'minus-circle',
            default => 'circle',
        };
    }

    /**
     * Get status color.
     */
    public function getColor(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'running' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'skipped' => 'yellow',
            default => 'gray',
        };
    }
}
