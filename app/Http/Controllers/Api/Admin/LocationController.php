<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * List all locations.
     */
    public function index(): JsonResponse
    {
        $locations = Location::withCount('nodes')
            ->get()
            ->map(fn ($location) => $this->formatLocation($location));

        return response()->json([
            'data' => $locations,
        ]);
    }

    /**
     * Get a single location.
     */
    public function show(Location $location): JsonResponse
    {
        $location->loadCount('nodes');

        return response()->json([
            'data' => $this->formatLocation($location),
        ]);
    }

    /**
     * Create a new location.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:10', 'unique:locations,short_code'],
            'description' => ['nullable', 'string'],
        ]);

        $location = Location::create($validated);

        return response()->json([
            'message' => 'Location created successfully',
            'data' => $this->formatLocation($location),
        ], 201);
    }

    /**
     * Update a location.
     */
    public function update(Request $request, Location $location): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'short_code' => ['sometimes', 'string', 'max:10', 'unique:locations,short_code,'.$location->id],
            'description' => ['nullable', 'string'],
        ]);

        $location->update($validated);

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => $this->formatLocation($location),
        ]);
    }

    /**
     * Delete a location.
     */
    public function destroy(Location $location): JsonResponse
    {
        // Check if location has nodes
        if ($location->nodes()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location with nodes. Please move or delete nodes first.',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully',
        ]);
    }

    /**
     * Format location for API response.
     */
    protected function formatLocation(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'short_code' => $location->short_code,
            'description' => $location->description,
            'nodes_count' => $location->nodes_count ?? 0,
            'created_at' => $location->created_at,
            'updated_at' => $location->updated_at,
        ];
    }
}
