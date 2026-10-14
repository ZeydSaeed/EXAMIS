<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * Synthetic accounts for Playwright: each test owns its own applicant and
 * each browser project its own block of 30, so tests stay independent (one application per applicant per period).
 * Every password is "password". Never runs in production.
 */
class EndToEndSeeder extends Seeder
{
    public const APPLICANTS = 120;

    public function run(): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('EndToEndSeeder must never run in production.');
        }

        $this->call([
            ReferenceDataSeeder::class,
            DocumentTypeSeeder::class,
            ContentPageSeeder::class,
            DemoSeeder::class,
        ]);

        foreach (range(1, self::APPLICANTS) as $number) {
            User::factory()->create([
                'username' => sprintf('e2e.applicant%03d', $number),
                'name' => 'طالب اختبار '.$number,
                'email' => 'e2e-family@examis.test',
            ]);
        }
    }
}
