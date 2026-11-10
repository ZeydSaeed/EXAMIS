<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The study step now asks only for the last school the student attended and
 * its governorate; the branch and specialization come from the choice made
 * after registration. The intermediate school, last grade, failed and left
 * years and the documents issued for study purposes are no longer collected
 * (restore from the backup taken before this migration if needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('school_governorate_code', 40)->nullable()->after('school_name');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['intermediate_school_name', 'last_grade', 'failed_years', 'left_years', 'issued_documents']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('intermediate_school_name', 150)->nullable()->after('school_name');
            $table->string('last_grade', 60)->nullable()->after('intermediate_school_name');
            $table->unsignedTinyInteger('failed_years')->nullable()->after('last_grade');
            $table->unsignedTinyInteger('left_years')->nullable()->after('failed_years');
            $table->json('issued_documents')->nullable()->after('other_info');
            $table->dropColumn('school_governorate_code');
        });
    }
};
