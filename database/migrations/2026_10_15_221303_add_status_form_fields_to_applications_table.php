<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields of the official "استمارة واقع حال" (student status form) that the
 * original schema did not have: surname, Karbala address parts, schooling
 * history and the documents the student was issued for study purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('surname', 60)->nullable()->after('full_name_ar');
            $table->string('district', 100)->nullable()->after('address');
            $table->string('mahalla', 10)->nullable()->after('district');
            $table->string('alley', 10)->nullable()->after('mahalla');
            $table->string('house_number', 10)->nullable()->after('alley');
            $table->string('intermediate_school_name', 150)->nullable()->after('school_name');
            $table->string('last_grade', 60)->nullable()->after('intermediate_school_name');
            $table->unsignedTinyInteger('failed_years')->nullable()->after('last_grade');
            $table->unsignedTinyInteger('left_years')->nullable()->after('failed_years');
            $table->string('other_info', 1000)->nullable()->after('previous_exam_year');
            $table->json('issued_documents')->nullable()->after('other_info');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'surname', 'district', 'mahalla', 'alley', 'house_number', 'intermediate_school_name',
                'last_grade', 'failed_years', 'left_years', 'other_info', 'issued_documents',
            ]);
        });
    }
};
