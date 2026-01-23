<?php

namespace App\Data\Deployment;

use App\Models\Deployment;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class DeploymentData extends Data
{
    public function __construct(
        public int $id,
        public string $uuid,
        public int $server_id,
        public string $status,
        public ?string $error,
        public ?string $started_at,
        public ?string $completed_at,
        public ?string $duration,
        public string $created_at,
        /** @var DataCollection<DeploymentStepData> */
        public ?DataCollection $steps,
    ) {}

    public static function fromModel(Deployment $deployment): self
    {
        $duration = null;
        if ($deployment->started_at && $deployment->completed_at) {
            $duration = $deployment->started_at->diffForHumans($deployment->completed_at, true);
        }

        return new self(
            id: $deployment->id,
            uuid: $deployment->uuid,
            server_id: $deployment->server_id,
            status: $deployment->status,
            error: $deployment->error,
            started_at: $deployment->started_at?->toIso8601String(),
            completed_at: $deployment->completed_at?->toIso8601String(),
            duration: $duration,
            created_at: $deployment->created_at->toIso8601String(),
            steps: $deployment->relationLoaded('steps')
                ? DeploymentStepData::collect($deployment->steps)
                : null,
        );
    }
}
