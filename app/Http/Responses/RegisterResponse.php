<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Sends a new applicant straight to their dashboard and shows the username
 * they will sign in with (ADR-0002), since there is no email verification
 * step that would repeat it.
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('auth.registered', ['username' => $request->user()?->username]),
            'duration' => 15000,
        ]);

        return redirect()->intended(Fortify::redirects('register'));
    }
}
