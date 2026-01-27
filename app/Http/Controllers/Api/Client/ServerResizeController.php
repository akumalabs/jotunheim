<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\Servers\ServerResizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerResizeController extends Controller
{
    public function __construct(
        private ServerResizeService $resizeService
    ) {}

    /**
     * Resize server resources (CPU, memory, disk).
     */
    public function resize(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot resize a suspended server',
            ], 403);
        }

        $validated = $request->validate([
            'cpu' => ['sometimes', 'integer', 'min:1', 'max:32'],
            'memory' => ['sometimes', 'integer', 'min:512', 'max:1024*1024'],
            'disk' => ['sometimes', 'integer', 'min:10', 'max:10240'],
        ]);

        $this->resizeService->resize($server, $validated);

        return response()->json([
            'message' => 'Server resized successfully',
        ]);
    }
}
