<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Node;
use App\Models\Server;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics and recent activity.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'servers' => [
                    'total' => Server::count(),
                    'active' => Server::where('status', 'running')->where('is_suspended', false)->count(),
                    'stopped' => Server::where('status', 'stopped')->where('is_suspended', false)->count(),
                    'suspended' => Server::where('is_suspended', true)->count(),
                ],
                'nodes' => [
                    'total' => Node::count(),
                    // Assuming non-maintenance nodes are online for now, as we don't have a live heartbeat in DB
                    'online' => Node::where('maintenance_mode', false)->count(),
                    'offline' => Node::where('maintenance_mode', true)->count(),
                ],
                'ips' => [
                    'total' => Address::count(),
                    'available' => Address::whereNull('server_id')->count(),
                    'assigned' => Address::whereNotNull('server_id')->count(),
                ],
            ],
            'recent_activities' => \App\Models\ActivityLog::with('user')
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'description' => $log->event,
                        'subject_type' => class_basename($log->subject_type),
                        'subject_id' => $log->subject_id,
                        'causer' => $log->user ? $log->user->name : 'System',
                        'created_at' => $log->created_at->diffForHumans(),
                        'status' => 'completed',
                    ];
                }),
            'recent_servers' => Server::with(['user', 'node.location', 'addresses'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($server) {
                    return [
                        'id' => $server->id,
                        'uuid' => $server->uuid,
                        'vmid' => $server->vmid,
                        'name' => $server->name,
                        'user' => $server->user ? $server->user->name : 'Unknown',
                        'node' => $server->node ? $server->node->name : 'Unknown',
                        'location_code' => $server->node && $server->node->location ? strtolower($server->node->location->short_code) : 'un',
                        'ip' => $server->addresses->first() ? $server->addresses->first()->address : '-',
                        'cpu' => $server->cpu,
                        'memory' => $server->formatted_memory,
                        'disk' => $server->formatted_disk,
                        'status' => $server->status,
                        'is_suspended' => $server->is_suspended,
                        'created_at' => $server->created_at->format('M d Y H:i'),
                    ];
                }),
        ]);
    }
}
