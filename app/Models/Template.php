<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_group_id',
        'name',
        'vmid',
        'min_cpu',
        'min_memory',
        'min_disk',
        'visible',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'min_cpu' => 'integer',
            'min_memory' => 'integer',
            'min_disk' => 'integer',
            'visible' => 'boolean',
            'order' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Template $template) {
            $template->uuid = $template->uuid ?? Str::uuid()->toString();
        });
    }

    /**
     * Get template group this template belongs to.
     */
    public function templateGroup(): BelongsTo
    {
        return $this->belongsTo(TemplateGroup::class);
    }

    /**
     * Get node this template is on (through template group).
     * Note: This relationship requires eager loading: Template::with('templateGroup.node')
     */
    public function node(): HasOneThrough
    {
        return $this->hasOneThrough(
            Node::class,           // Remote model (what we want)
            TemplateGroup::class,  // Intermediate model (through)
            'id',                    // First key on remote (nodes.id)
            'node_id',               // First key on intermediate (template_groups.node_id)
            'template_group_id',     // Second key on intermediate (template_groups.id)
            'id'                     // Second local key (defaults to templates.id)
        );
    }
}
