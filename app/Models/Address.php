<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'address_pool_id',
        'server_id',
        'address',
        'cidr',
        'gateway',
        'mac_address',
        'type',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'cidr' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Get the pool this address belongs to.
     */
    public function addressPool(): BelongsTo
    {
        return $this->belongsTo(AddressPool::class);
    }

    /**
     * Get the server this address is assigned to.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if this address is assigned to a server.
     */
    public function isAssigned(): bool
    {
        return $this->server_id !== null;
    }

    /**
     * Get the full CIDR notation (e.g., "192.168.1.1/24").
     */
    public function getFullAddressAttribute(): string
    {
        return "{$this->address}/{$this->cidr}";
    }
}
