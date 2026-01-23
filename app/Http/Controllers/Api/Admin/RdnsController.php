<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RdnsRecord;
use App\Models\Server;
use App\Services\Network\RdnsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RdnsController extends Controller
{
    public function __construct(
        private RdnsService $rdnsService
    ) {}

    public function index(): JsonResponse
    {
        $records = RdnsRecord::with('server.user')->get();

        return response()->json([
            'data' => $records->map(fn ($record) => [
                'id' => $record->id,
                'ip_address' => $record->ip_address,
                'ptr_record' => $record->ptr_record,
                'mode' => $record->mode,
                'verified' => $record->verified,
                'server' => [
                    'id' => $record->server->id,
                    'uuid' => $record->server->uuid,
                    'name' => $record->server->name,
                ],
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ]),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $record = RdnsRecord::with('server')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $record->id,
                'ip_address' => $record->ip_address,
                'ptr_record' => $record->ptr_record,
                'mode' => $record->mode,
                'verified' => $record->verified,
                'server' => [
                    'id' => $record->server->id,
                    'uuid' => $record->server->uuid,
                    'name' => $record->server->name,
                ],
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'ip_address' => ['required', 'ip'],
            'ptr_record' => ['required', 'string'],
            'mode' => ['sometimes', 'in:manual,automated'],
        ]);

        try {
            $server = Server::findOrFail($validated['server_id']);
            $record = $this->rdnsService->createRdnsRecord(
                $server,
                $validated['ip_address'],
                $validated['ptr_record'],
                $validated['mode'] ?? 'manual',
            );

            return response()->json([
                'message' => 'RDNS record created successfully',
                'data' => [
                    'id' => $record->id,
                    'ip_address' => $record->ip_address,
                    'ptr_record' => $record->ptr_record,
                    'mode' => $record->mode,
                    'verified' => $record->verified,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create RDNS record: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to create RDNS record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'ptr_record' => ['sometimes', 'string'],
            'verified' => ['sometimes', 'boolean'],
        ]);

        try {
            $record = RdnsRecord::findOrFail($id);

            $data = array_filter([
                'ptr_record' => $validated['ptr_record'] ?? null,
                'verified' => $validated['verified'] ?? null,
            ], fn ($value) => ! is_null($value));

            $record = $this->rdnsService->updateRdnsRecord($record, $data);

            return response()->json([
                'message' => 'RDNS record updated successfully',
                'data' => $record,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update RDNS record {$id}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to update RDNS record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $record = RdnsRecord::findOrFail($id);
            $this->rdnsService->deleteRdnsRecord($record);

            return response()->json([
                'message' => 'RDNS record deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete RDNS record {$id}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to delete RDNS record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        try {
            $record = RdnsRecord::findOrFail($id);
            $record = $this->rdnsService->verifyRdnsRecord($record);

            return response()->json([
                'message' => 'RDNS record verified successfully',
                'data' => $record,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to verify RDNS record {$id}: ".$e->getMessage());

            return response()->json([
                'message' => 'Failed to verify RDNS record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
        ]);

        try {
            $server = Server::with('addresses')->findOrFail($validated['server_id']);

            foreach ($server->addresses as $address) {
                $existingRecord = RdnsRecord::where('ip_address', $address->address)
                    ->where('server_id', '!=', $validated['server_id'])
                    ->first();

                if (! $existingRecord) {
                    RdnsRecord::create([
                        'server_id' => $server->id,
                        'ip_address' => $address->address,
                        'ptr_record' => $address->hostname ?? $address->address,
                        'mode' => 'automated',
                        'verified' => false,
                    ]);
                }
            }

            Log::info("Synced RDNS records for server {$server->uuid}");

            return response()->json([
                'message' => 'RDNS records synced successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync RDNS records: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to sync RDNS records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
