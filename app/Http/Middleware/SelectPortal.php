<?php

namespace App\Http\Middleware;

use App\Enums\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the guard and session settings for the host. Runs first in the web
 * group, because the session must not start with the other portal's cookie.
 */
class SelectPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $portal = Portal::forRequest($request);

        if ($portal !== null) {
            config($portal->sessionConfig($request->isSecure()));
            Auth::shouldUse($portal->guard());
        }

        return $next($request);
    }
}
