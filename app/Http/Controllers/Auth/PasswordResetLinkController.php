<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password reset requests are made by username because email addresses may
 * be shared between accounts (ADR-0002). The response never reveals whether
 * the account exists.
 */
class PasswordResetLinkController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:32'],
        ]);

        Password::broker()->sendResetLink(['username' => Str::lower($validated['username'])]);

        return back()->with('status', __('auth.reset_link_generic'));
    }
}
