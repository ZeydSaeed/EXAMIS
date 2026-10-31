<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that was deactivated while signed in, or of
 * a one-time student account that has closed. Until the print window ends, a
 * closed account can reach nothing but its final page and printable form: no
 * page behind it and no sign-out (ADR-0010).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        if ($user->isInPrintWindow() && ($finalPage = $this->finalPage($user)) !== null) {
            return $request->routeIs('applications.receipt', 'applications.printed') ? $next($request) : redirect($finalPage);
        }

        $message = $user->isClosed() ? __('auth.account_closed') : __('auth.deactivated');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('apply.terms')->with('status', $message);
    }

    private function finalPage(User $user): ?string
    {
        $application = $user->applications()->whereNotNull('reference_number')->latest('id')->first();

        return $application !== null ? route('applications.printed', $application) : null;
    }
}
