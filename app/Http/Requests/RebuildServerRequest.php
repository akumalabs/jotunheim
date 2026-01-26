<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Server;
use App\Models\Template;
use App\Rules\TemplateAvailableOnNode;
use App\Rules\TemplateFitsServerDisk;

class RebuildServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $server = $this->route('server');
        return $this->user()->can('rebuild', $server);
    }

    public function rules(): array
    {
        return [
            'template_vmid' => [
                'required',
                'exists:templates,vmid',
                new TemplateAvailableOnNode($this->route('server')),
                new TemplateFitsServerDisk($this->route('server')),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'preserve_backups' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_vmid.required' => 'Please select a template',
            'template_vmid.exists' => 'Selected template does not exist',
            'template_vmid.template_available_on_node' => 'Template is not available on this server\'s node',
            'template_vmid.template_fits_server_disk' => 'Template requires more disk space than server has allocated',
            'password.min' => 'Password must be at least 8 characters',
            'password.max' => 'Password must not exceed 72 characters',
        ];
    }
}
