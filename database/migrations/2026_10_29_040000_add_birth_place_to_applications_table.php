<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Place of birth: an Iraqi governorate code, or "OTHER" with the place
 * written in `birth_place_other`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('birth_place_code', 40)->nullable()->after('birth_date');
            $table->string('birth_place_other', 100)->nullable()->after('birth_place_code');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['birth_place_code', 'birth_place_other']);
        });
    }
};
