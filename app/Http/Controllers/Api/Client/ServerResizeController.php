<?php

namespace App\Http\Controllers\Api\Client;

use App\Jobs\Server\ResizeServerJob;
use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerResizeController extends Controller
{
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

        ResizeServerJob::dispatch($server, $validated);

        return response()->json([
            'message' => 'Server resize initiated',
            'status' => 'processing',
        ]);
    }
}
