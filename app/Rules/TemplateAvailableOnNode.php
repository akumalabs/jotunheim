<?php

namespace App\Rules;

use App\Models\Template;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TemplateAvailableOnNode implements ValidationRule
{
    public function __construct(private int $serverId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $template = Template::where('vmid', $value)
            ->with('templateGroup.node')
            ->first();
        
        if (!$template || !$template->templateGroup || $template->templateGroup->node_id !== $this->getServerNodeId()) {
            $fail('Template is not available on this server\'s node');
        }
    }

    private function getServerNodeId(): int
    {
        return \App\Models\Server::find($this->serverId)?->node_id ?? 0;
    }
}
