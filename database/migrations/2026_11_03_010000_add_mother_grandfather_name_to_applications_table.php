<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The third part of the mother's name, asked for on the form and listed in
 * the student export. Applications sent before this stay without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('mother_grandfather_name', 40)->nullable()->after('mother_father_name');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('mother_grandfather_name');
        });
    }
};
