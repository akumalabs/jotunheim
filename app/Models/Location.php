<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_code',
        'description',
    ];

    /**
     * Get the nodes in this location.
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    /**
     * Get the total number of servers in this location.
     */
    public function getServersCountAttribute(): int
    {
        return $this->nodes->sum(fn ($node) => $node->servers_count ?? $node->servers->count());
    }
}
