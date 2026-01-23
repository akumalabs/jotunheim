<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Servers\GuestAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GuestAgentController extends Controller
{
    public function __construct(
        private GuestAgentService $guestAgentService
    ) {}

    /**
     * Get guest agent info.
     */
    public function info(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot access guest agent of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $info = $service->getInfo();

            return response()->json([
                'data' => $info,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get guest agent info for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to get guest agent info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get OS information.
     */
    public function osInfo(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot access guest agent of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $osInfo = $service->getOsInfo();

            return response()->json([
                'data' => $osInfo,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get OS info for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to get OS info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get network interfaces.
     */
    public function network(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot access guest agent of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $interfaces = $service->getNetworkInterfaces();

            return response()->json([
                'data' => $interfaces,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get network interfaces for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to get network interfaces',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ping guest agent.
     */
    public function ping(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot access guest agent of a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $success = $service->ping();

            return response()->json([
                'message' => $success ? 'Guest agent is online' : 'Guest agent is offline',
                'data' => [
                    'online' => $success,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to ping guest agent for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to ping guest agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute command on guest agent.
     */
    public function exec(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot execute commands on a suspended server',
            ], 403);
        }

        $validated = $request->validate([
            'command' => ['required', 'string'],
            'args' => ['nullable', 'array'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $result = $service->executeCommand($validated['command'], $validated['args'] ?? []);

            return response()->json([
                'message' => 'Command executed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to execute command for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to execute command',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set user password via guest agent.
     */
    public function setPassword(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot set password on a suspended server',
            ], 403);
        }

        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $service->setUserPassword($validated['username'], $validated['password']);

            return response()->json([
                'message' => 'Password set successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to set password for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to set password',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Shutdown guest agent.
     */
    public function shutdown(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($server->is_suspended) {
            return response()->json([
                'message' => 'Cannot shutdown guest agent on a suspended server',
            ], 403);
        }

        try {
            $client = new ProxmoxApiClient($server->node);
            $service = new GuestAgentService($client, $server);

            $result = $service->shutdown();

            return response()->json([
                'message' => 'Guest agent shutdown initiated',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to shutdown guest agent for server {$server->uuid}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to shutdown guest agent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
