<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users.
     */
    public function index(): JsonResponse
    {
        $users = User::withCount('servers')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($user) => $this->formatUser($user));

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Get a single user.
     */
    public function show(User $user): JsonResponse
    {
        $user->loadCount('servers');

        return response()->json([
            'data' => $this->formatUser($user, true),
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'User created successfully',
            'data' => $this->formatUser($user),
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Cannot delete your own account',
            ], 422);
        }

        // Check if user has servers
        if ($user->servers()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete user with active servers',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Format user for API response.
     */
    protected function formatUser(User $user, bool $detailed = false): array
    {
        $data = [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'servers_count' => $user->servers_count ?? 0,
            'created_at' => $user->created_at,
        ];

        if ($detailed) {
            $data['email_verified_at'] = $user->email_verified_at;
            $data['two_factor_enabled'] = ! is_null($user->two_factor_confirmed_at);
        }

        return $data;
    }
}
