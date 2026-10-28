<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Student accounts have no password (ADR-0009), so password-protected
 * settings would trap them on the confirm-password screen. Their security
 * settings live on the student card instead.
 */
class RedirectPasswordlessStudents
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isApplicant() && ! $user->hasPassword()) {
            return redirect()->route('student.card');
        }

        return $next($request);
    }
}
