<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usernames may use Arabic letters (ء–غ, ف–ي) as well as lowercase English
 * letters, digits and . _ -, from three characters. Mirrors
 * ProfileValidationRules::usernameRules().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_format_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_format_check CHECK (username ~ '^[a-z0-9._\\u0621-\\u063A\\u0641-\\u064A-]{3,32}$')");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_format_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_username_format_check CHECK (username ~ '^[a-z0-9._-]{4,32}$')");
    }
};
