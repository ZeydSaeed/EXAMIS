<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * Synthetic staff for Playwright; students register through the browser.
 * Every password is "password". Never runs in production.
 */
class EndToEndSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('EndToEndSeeder must never run in production.');
        }

        $this->call([
            ReferenceDataSeeder::class,
            DocumentTypeSeeder::class,
            ContentPageSeeder::class,
            MessageTemplateSeeder::class,
            DemoSeeder::class,
        ]);

    }
}
