<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandwidthLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'bytes_in',
        'bytes_out',
        'total_bytes',
        'logged_at',
    ];

    protected $casts = [
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'total_bytes' => 'integer',
        'logged_at' => 'datetime',
    ];

    /**
     * Get the server that owns this bandwidth log.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
