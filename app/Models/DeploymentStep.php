<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'deployment_id',
        'name',
        'status',
        'output',
        'error',
        'order_column',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * The deployment this step belongs to.
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /**
     * Mark step as started.
     */
    public function start(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark step as completed.
     */
    public function complete(?string $output = null): void
    {
        $this->update([
            'status' => 'completed',
            'output' => $output,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark step as failed.
     */
    public function fail(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Skip this step.
     */
    public function skip(): void
    {
        $this->update([
            'status' => 'skipped',
            'completed_at' => now(),
        ]);
    }
}
