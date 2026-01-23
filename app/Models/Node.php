<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Node extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'fqdn',
        'port',
        'token_id',
        'token_secret',
        'memory',
        'memory_overallocate',
        'disk',
        'disk_overallocate',
        'cpu',
        'cpu_overallocate',
        'storage',
        'network',
        'cluster',
        'maintenance_mode',
    ];

    protected $hidden = [
        'token_secret',
    ];

    protected function casts(): array
    {
        return [
            'token_secret' => 'encrypted',
            'memory' => 'integer',
            'memory_overallocate' => 'integer',
            'disk' => 'integer',
            'disk_overallocate' => 'integer',
            'cpu' => 'integer',
            'cpu_overallocate' => 'integer',
            'maintenance_mode' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Node $node) {
            $node->uuid = $node->uuid ?? Str::uuid()->toString();
        });
    }

    /**
     * Get the location this node belongs to.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the servers on this node.
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Get the template groups for this node.
     */
    public function templateGroups(): HasMany
    {
        return $this->hasMany(TemplateGroup::class);
    }

    /**
     * Get the ISOs on this node.
     */
    public function isos(): HasMany
    {
        return $this->hasMany(Iso::class);
    }

    /**
     * Get the address pools linked to this node.
     */
    public function addressPools(): BelongsToMany
    {
        return $this->belongsToMany(AddressPool::class, 'address_pool_node');
    }

    /**
     * Get the memory available on this node (with overallocation).
     */
    protected function memoryAvailable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->memory + ($this->memory * $this->memory_overallocate / 100)
        );
    }

    /**
     * Get the disk available on this node (with overallocation).
     */
    protected function diskAvailable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->disk + ($this->disk * $this->disk_overallocate / 100)
        );
    }

    /**
     * Get the CPU available on this node (with overallocation).
     */
    protected function cpuAvailable(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cpu + ($this->cpu * $this->cpu_overallocate / 100)
        );
    }

    /**
     * Get the Proxmox API URL.
     */
    public function getApiUrl(): string
    {
        return "https://{$this->fqdn}:{$this->port}/api2/json";
    }
}
