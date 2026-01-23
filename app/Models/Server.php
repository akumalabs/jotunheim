<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'node_id',
        'vmid',
        'name',
        'hostname',
        'description',
        'cpu',
        'memory',
        'disk',
        'bandwidth_limit',
        'bandwidth_usage',
        'status',
        'is_suspended',
        'firewall_enabled',
        'is_installing',
        'installed_at',
        'installation_task',
    ];

    protected function casts(): array
    {
        return [
            'cpu' => 'integer',
            'memory' => 'integer',
            'disk' => 'integer',
            'bandwidth_limit' => 'integer',
            'bandwidth_usage' => 'integer',
            'is_suspended' => 'boolean',
            'firewall_enabled' => 'boolean',
            'is_installing' => 'boolean',
            'installed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Server $server) {
            $server->uuid = $server->uuid ?? Str::uuid()->toString();
        });
    }

    /**
     * Get the user who owns this server.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the node this server is on.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Get the addresses assigned to this server.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the firewall rules for this server.
     */
    public function firewallRules(): HasMany
    {
        return $this->hasMany(FirewallRule::class);
    }

    /**
     * Get the backups for this server.
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Get the deployments for this server.
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /**
     * Get the activity logs for this server.
     */
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /**
     * Get the primary address for this server.
     */
    public function primaryAddress()
    {
        return $this->addresses()->where('is_primary', true)->first();
    }

    /**
     * Get the bandwidth logs for this server.
     */
    public function bandwidthLogs()
    {
        return $this->hasMany(BandwidthLog::class);
    }

    /**
     * Get memory in human-readable format (e.g., "2 GB").
     */
    public function getFormattedMemoryAttribute(): string
    {
        return $this->formatBytes($this->memory);
    }

    /**
     * Get disk in human-readable format (e.g., "20 GB").
     */
    public function getFormattedDiskAttribute(): string
    {
        return $this->formatBytes($this->disk);
    }

    /**
     * Check if a server is running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if a server is stopped.
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
