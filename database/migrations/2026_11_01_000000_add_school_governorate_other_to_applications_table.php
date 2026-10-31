<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A last school outside the governorate list: `school_governorate_code` is
 * then "OTHER" and the governorate is written in `school_governorate_other`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('school_governorate_other', 100)->nullable()->after('school_governorate_code');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('school_governorate_other');
        });
    }
};
