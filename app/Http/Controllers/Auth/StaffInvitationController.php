<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Staff\ManageStaffAccess;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page behind a one-time staff link. The token travels in the URL
 * fragment, which browsers never send, so it stays out of access logs and
 * Referer headers; the page reads it and posts it.
 */
class StaffInvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(): Response
    {
        return Inertia::render('auth/staff-invitation', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Shows whose link this is, without spending it.
     */
    public function inspect(Request $request): Response
    {
        $request->validate(['token' => ['required', 'string', 'max:128']]);

        $invitation = StaffInvitation::query()
            ->with('user')
            ->open()
            ->where('token_hash', StaffInvitation::hashToken((string) $request->input('token')))
            ->first();

        return Inertia::render('auth/staff-invitation', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'invalid' => $invitation === null || ! $invitation->user->isActive(),
            'invitee' => $invitation === null ? null : ['name' => $invitation->user->name, 'email' => $invitation->user->email],
        ]);
    }

    public function store(Request $request, ManageStaffAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'password' => $this->passwordRules(),
        ]);

        $access->acceptInvitation($validated['token'], $validated['password']);

        return to_route('staff.login');
    }
}
