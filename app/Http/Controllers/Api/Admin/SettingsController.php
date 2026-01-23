<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Clear application cache.
     */
    public function clearCache(): JsonResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            return response()->json(['message' => 'Application cache cleared successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to clear cache'], 500);
        }
    }

    /**
     * Clear route cache.
     */
    public function clearRoute(): JsonResponse
    {
        try {
            Artisan::call('route:clear');
            return response()->json(['message' => 'Route cache cleared successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to clear route cache: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to clear route cache'], 500);
        }
    }

    /**
     * Clear config cache.
     */
    public function clearConfig(): JsonResponse
    {
        try {
            Artisan::call('config:clear');
            return response()->json(['message' => 'Config cache cleared successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to clear config cache: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to clear config cache'], 500);
        }
    }

    /**
     * Optimize application.
     */
    public function optimize(): JsonResponse
    {
        try {
            Artisan::call('optimize:clear');
            // We avoid running 'optimize' (which caches) in dev/dynamic environments via API usually, 
            // but if requested we can do it. For now, clear is safer to ensure fresh state.
            // But user asked for "Optimize". Let's do clear then optimize/cache if possible, 
            // or just clear all optimizations. 
            // 'optimize' usually caches config and routes.
            Artisan::call('optimize'); 
            return response()->json(['message' => 'Application minimized and optimized successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to optimize application: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to optimize application'], 500);
        }
    }
}
