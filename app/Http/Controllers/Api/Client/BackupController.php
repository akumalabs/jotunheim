<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Proxmox\ProxmoxApiClient;
use App\Services\Proxmox\ProxmoxApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    /**
     * List backups for a server.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $backups = $server->backups()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($backup) => $this->formatBackup($backup));

        return response()->json([
            'data' => $backups,
        ]);
    }

    /**
     * Create a new backup.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        // Check backup limit (optional)
        $maxBackups = 5; // Could be configurable
        if ($server->backups()->count() >= $maxBackups) {
            return response()->json([
                'message' => "Maximum of {$maxBackups} backups allowed. Please delete an old backup first.",
            ], 422);
        }

        try {
            $client = new ProxmoxApiClient($server->node);

            // Initiate backup on Proxmox
            $task = $client->createBackup((int) $server->vmid, $server->node->storage);

            // Create backup record
            $backup = $server->backups()->create([
                'name' => 'Backup '.now()->format('Y-m-d H:i'),
                'status' => 'pending',
            ]);

            // TODO: Queue job to monitor backup task and update status

            return response()->json([
                'message' => 'Backup started',
                'data' => $this->formatBackup($backup),
            ], 201);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to create backup',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a backup.
     */
    public function destroy(Request $request, string $uuid, Backup $backup): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        // Verify backup belongs to this server
        if ($backup->server_id !== $server->id) {
            return response()->json([
                'message' => 'Backup not found',
            ], 404);
        }

        // Check if locked
        if ($backup->is_locked) {
            return response()->json([
                'message' => 'This backup is locked and cannot be deleted',
            ], 422);
        }

        try {
            // Delete from Proxmox if volid exists
            if ($backup->volid) {
                $client = new ProxmoxApiClient($server->node);
                $client->deleteBackup($backup->volid);
            }

            $backup->delete();

            return response()->json([
                'message' => 'Backup deleted',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to delete backup',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Restore from a backup.
     */
    public function restore(Request $request, string $uuid, Backup $backup): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->with('node')
            ->firstOrFail();

        if ($backup->server_id !== $server->id) {
            return response()->json([
                'message' => 'Backup not found',
            ], 404);
        }

        if ($backup->status !== 'completed') {
            return response()->json([
                'message' => 'Cannot restore from incomplete backup',
            ], 422);
        }

        try {
            $client = new ProxmoxApiClient($server->node);

            // Stop server first if running
            $status = $client->getVMStatus((int) $server->vmid);
            if ($status['status'] === 'running') {
                $client->stopVM((int) $server->vmid);
                sleep(5); // Wait for shutdown
            }

            // Restore from backup
            $client->restoreBackup(
                (int) $server->vmid,
                $backup->volid,
                $server->node->storage
            );

            return response()->json([
                'message' => 'Restore initiated. The server will be available shortly.',
            ]);

        } catch (ProxmoxApiException $e) {
            return response()->json([
                'message' => 'Failed to restore backup',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle backup lock.
     */
    public function toggleLock(Request $request, string $uuid, Backup $backup): JsonResponse
    {
        $server = $request->user()
            ->servers()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($backup->server_id !== $server->id) {
            return response()->json([
                'message' => 'Backup not found',
            ], 404);
        }

        $backup->update(['is_locked' => ! $backup->is_locked]);

        return response()->json([
            'message' => $backup->is_locked ? 'Backup locked' : 'Backup unlocked',
            'data' => $this->formatBackup($backup),
        ]);
    }

    /**
     * Format backup for response.
     */
    protected function formatBackup(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'uuid' => $backup->uuid,
            'name' => $backup->name,
            'status' => $backup->status,
            'size' => $backup->size,
            'size_formatted' => $backup->formatted_size,
            'is_locked' => $backup->is_locked,
            'completed_at' => $backup->completed_at,
            'created_at' => $backup->created_at,
        ];
    }
}
