<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        foreach ($permissions as $permission) {
            if (!$request->user()->hasPermission($permission)) {
                return response()->json([
                    'message' => 'Forbidden',
                    'error' => "Missing permission: {$permission}"
                ], 403);
            }
        }

        return $next($request);
    }
}
