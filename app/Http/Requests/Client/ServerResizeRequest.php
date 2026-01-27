<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\BaseApiRequest;

class ServerResizeRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'cpu' => ['sometimes', 'integer', 'min:1', 'max:32'],
            'memory' => ['sometimes', 'integer', 'min:512', 'max:1073741824'],
            'disk' => ['sometimes', 'integer', 'min:10737418240', 'max:10737418240000'],
        ];
    }
}
