<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A one-time student account moves through its steps and nothing else: the
 * account pages (notifications, card, settings) are for staff. A student who
 * opens one is sent back to their next step (ADR-0010).
 */
class KeepStudentsOnTheirPath
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isApplicant()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
