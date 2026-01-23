<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'node_id',
        'name',
        'order',
        'visible',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'visible' => 'boolean',
        ];
    }

    /**
     * Get the node this template group belongs to.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Get the templates in this group.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class)->orderBy('order');
    }
}
