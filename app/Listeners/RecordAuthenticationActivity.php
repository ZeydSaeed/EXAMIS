<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Writes authentication events to the audit trail. Attempted emails are
 * stored only as a keyed hash so failed-login records can be correlated
 * without collecting what people typed.
 */
class RecordAuthenticationActivity
{
    public function __construct(private AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->record('auth.login', $event->user, actor: $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->audit->record('auth.failed', metadata: [
            'email_hash' => $this->hashIdentifier((string) ($event->credentials['email'] ?? '')),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->record('auth.lockout', metadata: [
            'email_hash' => $this->hashIdentifier((string) $event->request->input('email', '')),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.logout', $event->user, actor: $event->user);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->record('auth.two_factor_confirmed', $event->user, actor: $event->user);
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->audit->record('auth.two_factor_failed', $event->user);
    }

    private function hashIdentifier(string $identifier): string
    {
        return substr(hash_hmac('sha256', Str::lower($identifier), (string) config('app.key')), 0, 32);
    }
}
