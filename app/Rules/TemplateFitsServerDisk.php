<?php

namespace App\Rules;

use App\Models\Server;
use App\Models\Template;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TemplateFitsServerDisk implements ValidationRule
{
    public function __construct(private int $serverId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $template = Template::where('vmid', $value)->first();
        $server = Server::find($this->serverId);
        
        if (!$template || !$server) {
            $fail('Template requires more disk space than server has allocated');
            return;
        }
        
        if ($template->min_disk > $server->disk) {
            $fail('Template requires more disk space than server has allocated');
        }
    }

    public function message(): string
    {
        return 'Template requires more disk space than server has allocated';
    }
}
