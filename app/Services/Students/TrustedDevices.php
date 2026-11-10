<?php

namespace App\Services\Students;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Remembered student devices. The browser holds an encrypted, HttpOnly
 * cookie "<device id>|<random token>"; the database holds only the token's
 * SHA-256 hash, so a database leak does not let anyone in.
 */
class TrustedDevices
{
    public const COOKIE = 'examis_device';

    /**
     * Remember the current browser for this account and queue its cookie.
     */
    public function trust(Request $request, User $user): TrustedDevice
    {
        $token = Str::random(64);
        $days = (int) config('examis.students.trusted_device_days');

        $device = $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'last_used_at' => now(),
            'expires_at' => now()->addDays($days),
        ]);

        Cookie::queue(Cookie::make(
            self::COOKIE,
            $device->id.'|'.$token,
            minutes: $days * 24 * 60,
            secure: $request->isSecure() ?: null,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $device;
    }

    /**
     * Whether this browser is a remembered, unexpired device of the account.
     */
    public function recognizes(Request $request, User $user): bool
    {
        $device = $this->current($request);

        if ($device === null || $device->user_id !== $user->id) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    public function current(Request $request): ?TrustedDevice
    {
        $value = $request->cookie(self::COOKIE);

        if (! is_string($value) || ! str_contains($value, '|')) {
            return null;
        }

        [$id, $token] = explode('|', $value, 2);

        if (! ctype_digit($id) || $token === '') {
            return null;
        }

        $device = TrustedDevice::query()->usable()->find((int) $id);

        return $device !== null && hash_equals($device->token_hash, hash('sha256', $token)) ? $device : null;
    }

    /**
     * Forget every remembered device of the account, optionally keeping the
     * one making this request, and rotate the remember token so every
     * "remember me" session ends too. Returns how many devices were revoked.
     */
    public function revokeAll(User $user, ?TrustedDevice $except = null): int
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        return $user->trustedDevices()
            ->whereNull('revoked_at')
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['revoked_at' => now()]);
    }
}
