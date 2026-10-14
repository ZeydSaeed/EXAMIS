<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must enrol in two-factor authentication before using the admin area
 * when `examis.security.require_staff_two_factor` is enabled (default in
 * production).
 */
class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('examis.security.require_staff_two_factor') && $user !== null && $user->two_factor_confirmed_at === null) {
            return redirect()->route('security.edit')->with('status', __('auth.staff_two_factor_required'));
        }

        return $next($request);
    }
}
