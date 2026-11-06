<?php

namespace App\Http\Middleware;

use App\Models\User;
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

            return $application === null
                || $request->routeIs('applications.receipt', 'applications.printed', 'applications.finish')
                || $this->opensAnotherStudent($request, $user)
                ? $next($request)
                : to_route('applications.printed', $application);
        }

        $closed = $user->isClosed();
        $message = $closed ? __('auth.account_closed') : __('auth.deactivated');

        $staff = $user->isStaff();

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The pages behind this one must not come back from the browser's history.
        Inertia::clearHistory();

        // A student who finished printing is done, so back to the start page.
        return redirect()->route(match (true) {
            $staff => 'staff.login',
            $closed => 'start',
            default => 'apply.terms',
        })->with('status', $message);
    }

    /**
     * Another student's QR link on a shared device: the QR controller signs the
     * waiting student out, so the next student never sees their form. The
     * waiting student still lands on the final page when they sign in again.
     */
    private function opensAnotherStudent(Request $request, User $user): bool
    {
        return $request->routeIs('student.qr')
            && strtolower((string) $request->route('publicId')) !== $user->public_id;
    }
}
