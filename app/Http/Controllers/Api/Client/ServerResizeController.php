<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ServerResizeRequest;
use App\Models\Server;
use App\Services\Servers\ServerResizeService;
use Illuminate\Http\JsonResponse;

class ServerResizeController extends Controller
{
    public function __construct(
        private ServerResizeService $resizeService
    ) {}

    /**
     * Resize server resources (CPU, memory, disk).
     */
    public function resize(ServerResizeRequest $request, string $uuid): JsonResponse
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

        $validated = $request->validated();

        $this->resizeService->resize($server, $validated);

        return response()->json([
            'message' => 'Server resized successfully',
        ]);
    }
}
