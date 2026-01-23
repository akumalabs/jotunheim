<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRebuild
{
    public function handle(Request $request, Closure $next): Response
    {
        $server = $request->route('server');

        if ($server) {
            $limit = config('settings.rate_limit.rebuild_per_hour', 3);

            $key = "rebuild:{$server->id}:{$request->ip()}";

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return response()->json([
                    'message' => 'Too many rebuild attempts',
                    'error' => "Maximum of {$limit} rebuilds per hour allowed for this server",
                ], 429);
            }

            RateLimiter::hit($key, 3600); // 1 hour
        }

        return $next($request);
    }
}
