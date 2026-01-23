<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RdnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'ip_address',
        'ptr_record',
        'mode',
        'verified',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
