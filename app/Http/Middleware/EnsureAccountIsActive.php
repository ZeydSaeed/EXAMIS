<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that was deactivated while signed in, or of
 * a one-time student account that has closed. A student who has sent the
 * application but not finished printing can reach nothing but the final page
 * and the form: no page behind it and no sign-out (ADR-0010).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isActive()) {
            $application = $user->applicationAwaitingPrint();

            return $application === null || $request->routeIs('applications.receipt', 'applications.printed', 'applications.finish')
                ? $next($request)
                : to_route('applications.printed', $application);
        }

        $message = $user->isClosed() ? __('auth.account_closed') : __('auth.deactivated');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The pages behind this one must not come back from the browser's history.
        Inertia::clearHistory();

        return redirect()->route('apply.terms')->with('status', $message);
    }
}
