<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data is safe for every environment; demo accounts are only
     * seeded outside production.
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            DocumentTypeSeeder::class,
            ContentPageSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([DemoSeeder::class, DemoApplicationsSeeder::class]);
        }
    }
}
