<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Events to log.
     */
    protected array $logEvents = [
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log mutating requests
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $response;
        }

        // Only log successful requests
        if (! $response->isSuccessful()) {
            return $response;
        }

        // Get the action type
        $action = $this->logEvents[$request->method()] ?? 'unknown';

        // Extract resource info from route
        $routeName = $request->route()?->getName();
        $resource = $this->extractResource($routeName);

        if ($resource) {
            $this->logActivity($request, $action, $resource, $response);
        }

        return $response;
    }

    /**
     * Extract resource name from route name.
     */
    protected function extractResource(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        // Route names like: nodes.store, servers.update, etc.
        $parts = explode('.', $routeName);

        return $parts[0] ?? null;
    }

    /**
     * Log the activity.
     */
    protected function logActivity(Request $request, string $action, string $resource, Response $response): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        // Get subject from route parameters
        $subjectId = null;
        $subjectType = null;

        // Try to get model from route parameters
        $routeParams = $request->route()?->parameters() ?? [];
        foreach ($routeParams as $key => $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                $subjectType = get_class($value);
                $subjectId = $value->getKey();
                break;
            } elseif (is_numeric($value)) {
                $subjectId = $value;
                $subjectType = $this->guessModelClass($resource);
            }
        }

        // For create actions, try to get ID from response
        if ($action === 'create' && ! $subjectId) {
            $responseData = json_decode($response->getContent(), true);
            $subjectId = $responseData['data']['id'] ?? null;
            $subjectType = $this->guessModelClass($resource);
        }

        if (! $subjectType) {
            return;
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId ?? 0,
            'event' => "{$resource}:{$action}",
            'properties' => [
                'method' => $request->method(),
                'path' => $request->path(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Guess model class from resource name.
     */
    protected function guessModelClass(string $resource): ?string
    {
        $map = [
            'nodes' => \App\Models\Node::class,
            'servers' => \App\Models\Server::class,
            'users' => \App\Models\User::class,
            'locations' => \App\Models\Location::class,
            'templates' => \App\Models\Template::class,
            'address-pools' => \App\Models\AddressPool::class,
        ];

        return $map[$resource] ?? null;
    }
}
