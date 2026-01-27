<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseApiRequest;

class ServerUpdateResourcesRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'cpu' => ['sometimes', 'integer', 'min:1', 'max:128'],
            'memory' => ['sometimes', 'integer', 'min:536870912'],
            'disk' => ['sometimes', 'integer', 'min:0'],
            'bandwidth_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
