<?php

namespace App\Http\Middleware;

use App\Enums\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the guard and session settings for the host. It is global middleware
 * because route middleware is too late: building a Fortify controller builds
 * the guard, which builds the session store from whatever config is set then.
 * Page history is encrypted wherever the browser can do it: Inertia stops
 * navigating if it is asked to on plain HTTP, which has no Web Crypto.
 */
class SelectPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $portal = Portal::forRequest($request);

        if ($portal !== null) {
            config($portal->sessionConfig($request->isSecure()));
            config(['inertia.history.encrypt' => $request->isSecure() || str_ends_with($request->getHost(), '.localhost')]);
            Auth::shouldUse($portal->guard());
        }

        return $next($request);
    }
}
