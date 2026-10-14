<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

/**
 * Writes authentication events to the audit trail. Attempted usernames are
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
            'username_hash' => $this->hashIdentifier((string) ($event->credentials['username'] ?? '')),
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->record('auth.lockout', metadata: [
            'username_hash' => $this->hashIdentifier((string) $event->request->input('username', '')),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.logout', $event->user, actor: $event->user);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.password_reset', $event->user, actor: $event->user);
        }
    }

    private function hashIdentifier(string $identifier): string
    {
        return substr(hash_hmac('sha256', Str::lower($identifier), (string) config('app.key')), 0, 32);
    }
}
