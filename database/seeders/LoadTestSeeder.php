<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Synthetic applicant accounts for k6 (tests/load). Usernames are
 * loadtest.0001 … loadtest.NNNN, password "password". Count comes from
 * LOAD_TEST_USERS (default 1000). Refuses to run in production.
 *
 * php artisan db:seed --class=LoadTestSeeder
 */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('LoadTestSeeder must never run in production.');
        }

        $count = (int) config('examis.load_test_users');
        $password = Hash::make('password');
        $now = now();

        $this->call([ReferenceDataSeeder::class, DocumentTypeSeeder::class, ContentPageSeeder::class, DemoSeeder::class]);

        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            DB::table('users')->insertOrIgnore(array_map(fn (int $number): array => [
                'username' => sprintf('loadtest.%04d', $number),
                'name' => 'مستخدم اختبار حمل '.$number,
                'email' => 'loadtest@examis.test',
                'email_verified_at' => $now,
                'password' => $password,
                'account_type' => 'applicant',
                'locale' => 'ar',
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $this->command->info(User::query()->where('username', 'like', 'loadtest.%')->count().' load-test applicants ready.');
    }
}
