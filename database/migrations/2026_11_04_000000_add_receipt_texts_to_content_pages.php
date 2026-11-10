<?php

use Database\Seeders\ContentPageSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The printed form's texts become editable under Settings; existing
     * databases get their pages here, a fresh install from the seeder.
     */
    public function up(): void
    {
        ContentPageSeeder::seedReceiptTexts();
    }

    public function down(): void
    {
        // The pages stay; the printed form reads them only while they exist.
    }
};
