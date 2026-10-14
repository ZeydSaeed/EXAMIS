<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies named rate limiters to Fortify endpoints that Fortify itself does
 * not throttle. Registered through `fortify.middleware`, so it survives
 * route caching.
 */
class ThrottleAuthEndpoints
{
    /**
     * Route name => rate limiter name.
     */
    private const LIMITERS = [
        'register.store' => 'registration',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[(string) $request->route()?->getName()] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
