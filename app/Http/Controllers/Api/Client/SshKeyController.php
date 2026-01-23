<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\SshKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SshKeyController extends Controller
{
    /**
     * List user's SSH keys.
     */
    public function index(Request $request): JsonResponse
    {
        $keys = $request->user()
            ->sshKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($key) => $this->formatKey($key));

        return response()->json([
            'data' => $keys,
        ]);
    }

    /**
     * Add a new SSH key.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'public_key' => ['required', 'string'],
        ]);

        // Validate it's a valid SSH key format
        if (! preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp\d+)\s+/', $validated['public_key'])) {
            return response()->json([
                'message' => 'Invalid SSH public key format',
            ], 422);
        }

        // Check for duplicates
        $fingerprint = SshKey::generateFingerprint($validated['public_key']);
        if ($request->user()->sshKeys()->where('fingerprint', $fingerprint)->exists()) {
            return response()->json([
                'message' => 'This SSH key already exists',
            ], 422);
        }

        $key = $request->user()->sshKeys()->create([
            'name' => $validated['name'],
            'public_key' => trim($validated['public_key']),
        ]);

        return response()->json([
            'message' => 'SSH key added',
            'data' => $this->formatKey($key),
        ], 201);
    }

    /**
     * Delete an SSH key.
     */
    public function destroy(Request $request, SshKey $sshKey): JsonResponse
    {
        // Ensure key belongs to user
        if ($sshKey->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'SSH key not found',
            ], 404);
        }

        $sshKey->delete();

        return response()->json([
            'message' => 'SSH key deleted',
        ]);
    }

    /**
     * Format SSH key for response.
     */
    protected function formatKey(SshKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'fingerprint' => $key->fingerprint,
            'public_key' => $key->public_key,
            'created_at' => $key->created_at,
        ];
    }
}
