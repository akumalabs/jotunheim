<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirewallRule extends Model
{
    protected $fillable = [
        'server_id',
        'name',
        'priority',
        'direction',
        'action',
        'protocol',
        'source_address',
        'source_port',
        'dest_address',
        'dest_port',
        'ip_version',
        'enabled',
        'position',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'position' => 'integer',
        'source_port' => 'integer',
        'dest_port' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
