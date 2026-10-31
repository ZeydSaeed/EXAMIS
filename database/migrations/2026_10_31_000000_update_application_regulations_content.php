<?php

use App\Models\ContentPage;
use Database\Seeders\ContentPageSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The seeder keeps pages an administrator has edited, so the new
     * regulations reach existing databases here. A fresh install has no page
     * yet and gets it from the seeder.
     */
    public function up(): void
    {
        [$titleAr, $titleEn, $bodyAr, $bodyEn] = ContentPageSeeder::ELIGIBILITY;

        ContentPage::query()->where('slug', 'eligibility')->update([
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'body_ar' => $bodyAr,
            'body_en' => $bodyEn,
        ]);
    }

    public function down(): void
    {
        // The previous wording is not kept.
    }
};
