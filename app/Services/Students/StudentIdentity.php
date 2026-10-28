<?php

namespace App\Services\Students;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Permanent identity of a student account (ADR-0009): the QR public id, the
 * five-digit student number, the private access code and the lookup of an
 * account by any one of its sign-in identifiers.
 */
class StudentIdentity
{
    /**
     * Unambiguous alphabet for access codes (no 0/O, 1/I/L).
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const RANDOM_NUMBER_ATTEMPTS = 25;

    /**
     * Normalize a mobile number to digits with an optional leading "+":
     * Arabic-Indic digits are converted and spaces, dashes and brackets
     * dropped; a "00" international prefix becomes "+".
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $phone = (string) preg_replace('/[\s\-().]/u', '', trim($phone));

        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        return $phone === '' ? null : $phone;
    }

    /**
     * Find an active-or-not applicant by username, registered mobile number
     * or student number. A five-digit value is a student number; anything
     * else made of digits is a mobile number. Staff never match here.
     */
    public function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $query = User::query()->where('account_type', AccountType::Applicant);
        $phone = self::normalizePhone($identifier);

        if ($phone !== null && preg_match('/^[0-9]{5}$/', $phone) === 1) {
            return $query->where('student_number', (int) $phone)->first();
        }

        if ($phone !== null && preg_match('/^\+?[0-9]{10,15}$/', $phone) === 1) {
            return $query->where('phone', $phone)->first();
        }

        return $query->where('username', Str::lower($identifier))->first();
    }

    public function findByPublicId(string $publicId): ?User
    {
        if (! Str::isUuid($publicId)) {
            return null;
        }

        return User::query()
            ->where('account_type', AccountType::Applicant)
            ->where('public_id', Str::lower($publicId))
            ->first();
    }

    /**
     * Give the account its permanent QR id and student number if it has
     * none yet (new registrations, and older accounts on first use).
     */
    public function ensureIdentity(User $user): void
    {
        if ($user->public_id === null) {
            $user->forceFill(['public_id' => (string) Str::uuid()])->save();
        }

        if ($user->student_number === null) {
            $this->assignStudentNumber($user);
        }
    }

    /**
     * Pick a free five-digit number. Uniqueness is enforced by the database:
     * a concurrent registration that takes the same number makes the UPDATE
     * fail and we try another. Each attempt runs in its own savepoint so a
     * PostgreSQL unique violation does not abort the outer transaction. When
     * the range is exhausted the account keeps working without a number.
     */
    public function assignStudentNumber(User $user): ?int
    {
        $min = (int) config('examis.students.student_number_min');
        $max = (int) config('examis.students.student_number_max');

        for ($attempt = 0; $attempt < self::RANDOM_NUMBER_ATTEMPTS; $attempt++) {
            if ($this->tryStudentNumber($user, random_int($min, $max))) {
                return $user->student_number;
            }
        }

        // The range is crowded: scan for the lowest free numbers instead of guessing.
        foreach ($this->freeStudentNumbers($min, $max) as $candidate) {
            if ($this->tryStudentNumber($user, $candidate)) {
                return $user->student_number;
            }
        }

        Log::warning('Student number range exhausted; account created without a student number.', ['user_id' => $user->id]);

        return null;
    }

    /**
     * Issue a new private access code, replacing any previous one. The plain
     * code is returned for display and stored as plain text, as the owner
     * decided (ADR-0009): anyone who can read the users table or a backup
     * can read every code.
     */
    public function issueAccessCode(User $user): string
    {
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        $code = substr($code, 0, 5).'-'.substr($code, 5);

        $user->forceFill([
            'access_code' => $code,
            'access_code_issued_at' => now(),
        ])->save();

        return $code;
    }

    public function checkAccessCode(User $user, string $code): bool
    {
        $code = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));

        if ($user->access_code === null || strlen($code) !== 10) {
            return false;
        }

        $code = substr($code, 0, 5).'-'.substr($code, 5);

        // Codes issued before they were stored in plain text are hashes.
        if (str_starts_with($user->access_code, '$')) {
            return Hash::check($code, $user->access_code);
        }

        return hash_equals($user->access_code, $code);
    }

    private function tryStudentNumber(User $user, int $candidate): bool
    {
        try {
            DB::transaction(function () use ($user, $candidate): void {
                $updated = User::query()
                    ->whereKey($user->id)
                    ->whereNull('student_number')
                    ->update(['student_number' => $candidate]);

                if ($updated === 1) {
                    $user->forceFill(['student_number' => $candidate])->syncOriginalAttribute('student_number');
                }
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($user->student_number === null) {
            // Another request assigned this account a number in the meantime.
            $user->student_number = User::query()->whereKey($user->id)->value('student_number');
            $user->syncOriginalAttribute('student_number');
        }

        return $user->student_number !== null;
    }

    /**
     * @return list<int>
     */
    private function freeStudentNumbers(int $min, int $max): array
    {
        $taken = array_flip(User::query()->whereNotNull('student_number')->pluck('student_number')->all());
        $free = [];

        for ($number = $min; $number <= $max && count($free) < 20; $number++) {
            if (! isset($taken[$number])) {
                $free[] = $number;
            }
        }

        return $free;
    }
}
