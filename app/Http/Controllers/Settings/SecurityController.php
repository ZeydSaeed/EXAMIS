<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Staff\RecoveryCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request, RecoveryCodes $recoveryCodes): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['recoveryCodesIssued'] = $request->user()->staffRecoveryCodes()->exists();
            $props['recoveryCodesRemaining'] = $recoveryCodes->remaining($request->user());
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Shows the recovery codes for the one time they exist in clear. Only an
     * administrator's reset allows a new set.
     */
    public function issueRecoveryCodes(Request $request, RecoveryCodes $recoveryCodes): JsonResponse
    {
        $user = $request->user();

        abort_if($user->two_factor_confirmed_at === null, 403);

        $codes = $recoveryCodes->issueOnce($user);

        abort_if($codes === null, 403);

        return response()->json(['codes' => $codes])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Update the user's password and end their other sessions.
     */
    public function update(PasswordUpdateRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        DB::table('staff_sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $audit->record('auth.password_changed', $user, actor: $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
