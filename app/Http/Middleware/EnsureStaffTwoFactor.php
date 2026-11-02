<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff must enrol in two-factor authentication before reaching anything but
 * the security page. There is no switch to turn this off.
 */
class EnsureStaffTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStaff() && $user->two_factor_confirmed_at === null) {
            return redirect()->route('security.edit')->with('status', __('auth.staff_two_factor_required'));
        }

        return $next($request);
    }
}
