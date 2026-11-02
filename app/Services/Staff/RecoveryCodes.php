<?php

namespace App\Services\Staff;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\RecoveryCode;

/**
 * One-time recovery codes for staff. Fortify keeps its codes decryptable and
 * shows them again on request; here only a keyed hash is stored, the plain
 * codes are shown once, and a new set needs an administrator's reset.
 */
class RecoveryCodes
{
    private const COUNT = 8;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @return list<string>|null the plain codes, or null if a set was already issued
     */
    public function issueOnce(User $user): ?array
    {
        return DB::transaction(function () use ($user): ?array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($user->staffRecoveryCodes()->exists()) {
                return null;
            }

            $codes = [];

            for ($i = 0; $i < self::COUNT; $i++) {
                $codes[] = RecoveryCode::generate();
            }

            $user->staffRecoveryCodes()->createMany(array_map(
                fn (string $code): array => ['code_hash' => $this->hash($code)],
                $codes,
            ));

            $user->forceFill(['two_factor_recovery_codes' => null])->save();
            $this->audit->record('staff.recovery_codes_issued', $user, ['count' => self::COUNT], $user);

            return $codes;
        });
    }

    /**
     * Spends a code. The conditional update lets only one of two simultaneous
     * uses through.
     */
    public function consume(User $user, string $code): bool
    {
        $used = $user->staffRecoveryCodes()
            ->where('code_hash', $this->hash(trim($code)))
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        if ($used !== 1) {
            return false;
        }

        $this->audit->record('auth.recovery_code_used', $user, ['remaining' => $this->remaining($user)], $user);

        return true;
    }

    public function remaining(User $user): int
    {
        return $user->staffRecoveryCodes()->whereNull('used_at')->count();
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
