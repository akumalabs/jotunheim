<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * List activity logs with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by event
        if ($request->has('event')) {
            $query->where('event', 'like', "%{$request->event}%");
        }

        // Filter by subject
        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Pagination
        $perPage = min($request->get('per_page', 25), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get activity for a specific subject.
     */
    public function forSubject(Request $request): JsonResponse
    {
        $request->validate([
            'subject_type' => ['required', 'string'],
            'subject_id' => ['required', 'integer'],
        ]);

        $logs = ActivityLog::with('user:id,name,email')
            ->where('subject_type', $request->subject_type)
            ->where('subject_id', $request->subject_id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $logs,
        ]);
    }
}
