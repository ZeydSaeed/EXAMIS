<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * Synthetic staff for Playwright, enrolled with a shared test-only authenticator
 * secret. Every password is "password". Never runs in production.
 */
class EndToEndSeeder extends Seeder
{
    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

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

        // Students register through the browser; only staff start out enrolled.
        User::query()->staff()->each(fn (User $user) => $user->forceFill([
            'two_factor_secret' => encrypt(self::TOTP_SECRET),
            'two_factor_confirmed_at' => now(),
        ])->save());
    }
}
