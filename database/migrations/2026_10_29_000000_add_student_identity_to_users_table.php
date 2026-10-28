<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student accounts (ADR-0009): applicants register with a username and a
 * mobile number only, and sign in with any one identifier plus a trusted
 * device or their private access code. Purely additive; existing rows keep
 * their password and email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->uuid('public_id')->nullable()->unique();
            $table->unsignedInteger('student_number')->nullable()->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('access_code')->nullable();
            $table->timestamp('access_code_issued_at')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_student_number_range_check CHECK (student_number BETWEEN 10000 AND 99999)');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_phone_format_check CHECK (phone ~ '^\\+?[0-9]{10,15}$')");
        }

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent', 250)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_student_number_range_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_phone_format_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropUnique(['student_number']);
            $table->dropUnique(['phone']);
            $table->dropColumn(['public_id', 'student_number', 'phone', 'access_code', 'access_code_issued_at']);
        });
    }
};
