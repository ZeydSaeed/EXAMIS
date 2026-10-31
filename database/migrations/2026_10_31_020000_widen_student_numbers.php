<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New student numbers have six digits, because every registration takes one
 * and none is ever reused (ADR-0010). Numbers already issued keep their five
 * digits: they are printed on forms. The interface is Arabic only now, so
 * nobody may stay on the English preference with no way back.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('locale', '<>', 'ar')->update(['locale' => 'ar']);

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_student_number_range_check');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_student_number_range_check CHECK (student_number BETWEEN 10000 AND 999999)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_student_number_range_check');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_student_number_range_check CHECK (student_number BETWEEN 10000 AND 99999) NOT VALID');
    }
};
