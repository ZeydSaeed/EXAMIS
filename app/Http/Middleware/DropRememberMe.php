<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff sign in afresh every time: "remember me" would bring an account back
 * without its password. Registered through `fortify.middleware`, so it
 * survives route caching.
 */
class DropRememberMe
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() === 'login.store') {
            $request->request->remove('remember');
        }

        return $next($request);
    }
}
