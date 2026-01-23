<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class ActivityService
{
    protected ?string $batch = null;

    protected ?Model $subject = null;

    protected ?int $userId = null;

    protected array $properties = [];

    /**
     * Start a new activity batch.
     */
    public function batch(): self
    {
        $this->batch = (string) Str::uuid();

        return $this;
    }

    /**
     * Set the subject of the activity.
     */
    public function subject(Model $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the user performing the action.
     */
    public function performedBy(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Add properties to the activity.
     */
    public function withProperties(array $properties): self
    {
        $this->properties = array_merge($this->properties, $properties);

        return $this;
    }

    /**
     * Log the activity.
     */
    public function log(string $event): ActivityLog
    {
        $log = ActivityLog::create([
            'batch' => $this->batch,
            'user_id' => $this->userId ?? Auth::id(),
            'subject_type' => $this->subject ? get_class($this->subject) : null,
            'subject_id' => $this->subject?->getKey(),
            'event' => $event,
            'properties' => ! empty($this->properties) ? $this->properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);

        // Reset state for next log
        $this->reset();

        return $log;
    }

    /**
     * Reset the service state.
     */
    protected function reset(): void
    {
        $this->subject = null;
        $this->userId = null;
        $this->properties = [];
        // Keep batch for chained logs
    }

    /**
     * Create a new instance.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Quick log for a server action.
     */
    public static function forServer(Model $server, string $event, array $properties = []): ActivityLog
    {
        return self::make()
            ->subject($server)
            ->withProperties($properties)
            ->log($event);
    }

    /**
     * Quick log for a user action.
     */
    public static function forUser(string $event, array $properties = []): ActivityLog
    {
        return self::make()
            ->withProperties($properties)
            ->log($event);
    }
}
