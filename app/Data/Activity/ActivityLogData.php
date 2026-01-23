<?php

namespace App\Data\Activity;

use App\Models\ActivityLog;
use Spatie\LaravelData\Data;

class ActivityLogData extends Data
{
    public function __construct(
        public int $id,
        public ?int $user_id,
        public ?string $user_name,
        public string $event,
        public ?string $subject_type,
        public ?int $subject_id,
        public ?string $subject_name,
        public ?array $properties,
        public ?string $ip_address,
        public string $created_at,
        public string $time_ago,
    ) {}

    public static function fromModel(ActivityLog $log): self
    {
        $user = $log->user;
        $subject = $log->subject;

        return new self(
            id: $log->id,
            user_id: $log->user_id,
            user_name: $user?->name ?? 'System',
            event: $log->event,
            subject_type: $log->subject_type ? class_basename($log->subject_type) : null,
            subject_id: $log->subject_id,
            subject_name: $subject?->name ?? null,
            properties: $log->properties,
            ip_address: $log->ip_address,
            created_at: $log->created_at->toIso8601String(),
            time_ago: $log->created_at->diffForHumans(),
        );
    }

    /**
     * Get human-readable event description.
     */
    public function getDescription(): string
    {
        $parts = explode(':', $this->event);
        $action = end($parts);

        return str_replace(['.', '_'], ' ', ucfirst($action));
    }
}
