<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddressPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the nodes linked to this pool.
     */
    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class, 'address_pool_node');
    }

    /**
     * Get the addresses in this pool.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get available (unassigned) addresses.
     */
    public function availableAddresses(): HasMany
    {
        return $this->addresses()->whereNull('server_id');
    }

    /**
     * Get the count of available addresses.
     */
    public function getAvailableCountAttribute(): int
    {
        return $this->availableAddresses()->count();
    }
}
