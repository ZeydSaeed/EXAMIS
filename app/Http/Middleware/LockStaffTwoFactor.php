<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor authentication is set up once and only an administrator can
 * reset it. Closes the Fortify endpoints that would let a signed-in account
 * turn it off, replace it or read its secret, and drops "remember me", which
 * would bring an account back without a password or a code. Registered
 * through `fortify.middleware`, so it survives route caching.
 */
class LockStaffTwoFactor
{
    /**
     * @var list<string>
     */
    private const CLOSED = ['two-factor.disable', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes'];

    /**
     * @var list<string>
     */
    private const CLOSED_ONCE_CONFIRMED = ['two-factor.enable', 'two-factor.qr-code', 'two-factor.secret-key'];

    public function handle(Request $request, Closure $next): Response
    {
        $route = (string) $request->route()?->getName();

        if ($route === 'login.store') {
            $request->request->remove('remember');
        }

        $confirmed = $request->user()?->two_factor_confirmed_at !== null;

        abort_if(in_array($route, self::CLOSED, true) || ($confirmed && in_array($route, self::CLOSED_ONCE_CONFIRMED, true)), 403);

        return $next($request);
    }
}
