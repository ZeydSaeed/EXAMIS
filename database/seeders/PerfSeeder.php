<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\Support\SyntheticTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Synthetic accounts for the quality-engineering suite (tests/load, k6),
 * matching the current sign-in model (ADR-0009, ADR-0010): password-less
 * one-time students who enter by QR link plus access code, and staff who
 * sign in by email and password. Everything is derived from a number, so k6
 * computes the same values without reading the database:
 *
 *   student n: QR /s/00000000-0000-4000-8000-<n, 12 digits>
 *              access code "PERF" + n as 6 digits, written PERFd-ddddd
 *              student number 900000 + n, phone 0000<n, 8 digits>
 *   staff:     perf.reviewer.<i>@qe.localhost, perf.supervisor.<i>@qe.localhost
 *
 * The process variables QE_STUDENTS (default LOAD_TEST_USERS) and
 * QE_STAFF_PASSWORD, which only the operator knows, set the count and
 * create the staff accounts; they work with a cached config. Runs only where
 * SyntheticTarget allows. Re-running adds what is missing and never resets a
 * used account: a student who submitted stays on the printed page, so rebuild
 * the test database between runs.
 *
 * php artisan db:seed --class=PerfSeeder
 */
class PerfSeeder extends Seeder
{
    public const MAX_STUDENTS = 50_000;

    public const STUDENT_NUMBER_BASE = 900_000;

    public const PUBLIC_ID_PREFIX = '00000000-0000-4000-8000-';

    public const STAFF_DOMAIN = 'qe.localhost';

    public const REVIEWERS = 5;

    public const SUPERVISORS = 2;

    public static function publicId(int $n): string
    {
        return self::PUBLIC_ID_PREFIX.sprintf('%012d', $n);
    }

    public static function accessCode(int $n): string
    {
        $code = sprintf('PERF%06d', $n);

        return substr($code, 0, 5).'-'.substr($code, 5);
    }

    public static function studentNumber(int $n): int
    {
        return self::STUDENT_NUMBER_BASE + $n;
    }

    /** No real Iraqi number starts with 0000. */
    public static function phone(int $n): string
    {
        return sprintf('0000%08d', $n);
    }

    public function run(): void
    {
        SyntheticTarget::assert();

        $count = (int) (getenv('QE_STUDENTS') ?: config('examis.load_test_users'));

        if ($count < 1 || $count > self::MAX_STUDENTS) {
            throw new InvalidArgumentException('QE_STUDENTS must be between 1 and '.self::MAX_STUDENTS.'.');
        }

        $this->call([
            ReferenceDataSeeder::class,
            DocumentTypeSeeder::class,
            ContentPageSeeder::class,
            MessageTemplateSeeder::class,
            DemoSeeder::class,
        ]);

        $this->seedStudents($count);
        $this->seedStaff((string) getenv('QE_STAFF_PASSWORD'));
    }

    private function seedStudents(int $count): void
    {
        $now = now();

        foreach (array_chunk(range(1, $count), 1000) as $chunk) {
            DB::table('users')->insertOrIgnore(array_map(fn (int $n): array => [
                'username' => null,
                'name' => (string) self::studentNumber($n),
                'email' => null,
                'password' => null,
                'account_type' => AccountType::Applicant->value,
                'locale' => 'ar',
                'phone' => self::phone($n),
                'public_id' => self::publicId($n),
                'student_number' => self::studentNumber($n),
                'access_code' => self::accessCode($n),
                'access_code_issued_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $ready = User::query()->where('public_id', 'like', self::PUBLIC_ID_PREFIX.'%')->count();

        $this->command->info("{$ready} synthetic students ready.");
    }

    private function seedStaff(string $password): void
    {
        if ($password === '') {
            $this->command->warn('QE_STAFF_PASSWORD is empty: no synthetic staff accounts were created.');

            return;
        }

        if (strlen($password) < 12) {
            throw new InvalidArgumentException('QE_STAFF_PASSWORD must be at least 12 characters.');
        }

        $hash = Hash::make($password);

        foreach ([[Role::Reviewer, 'reviewer', self::REVIEWERS], [Role::Supervisor, 'supervisor', self::SUPERVISORS]] as [$role, $name, $total]) {
            for ($i = 1; $i <= $total; $i++) {
                $email = "perf.{$name}.{$i}@".self::STAFF_DOMAIN;

                if (User::query()->where('email', $email)->exists()) {
                    continue;
                }

                $user = (new User)->forceFill([
                    'username' => "perf.{$name}.{$i}",
                    'name' => "perf {$name} {$i}",
                    'email' => $email,
                    'password' => $hash,
                    'account_type' => AccountType::Staff,
                    'locale' => 'ar',
                ]);
                $user->save();
                $user->roleAssignments()->create(['role' => $role]);
            }
        }

        $this->command->info('Synthetic staff ready: '.(self::REVIEWERS + self::SUPERVISORS).' accounts.');
    }
}
