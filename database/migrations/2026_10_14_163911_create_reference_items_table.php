<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable lookup lists (governorates, directorates, study branches,
     * applicant categories) so eligibility data is not hard-coded.
     */
    public function up(): void
    {
        Schema::create('reference_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('code', 40);
            $table->string('parent_code', 40)->nullable();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reference_items');
    }
};
