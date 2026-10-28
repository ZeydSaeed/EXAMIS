<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes sign-in data that ADR-0009 no longer uses:
 *
 * - `users.email_verified_at`: email verification is disabled and nothing
 *   is mailed to applicants any more.
 * - `passkeys`: passkey sign-in was removed.
 * - Applicant passwords, emails and remember tokens: students sign in with
 *   an identifier plus a trusted device or access code. Staff keep theirs.
 *
 * The cleared applicant data cannot be restored by `down()`; restore it from
 * the backup taken before this migration if needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('account_type', 'applicant')
            ->update(['password' => null, 'email' => null, 'remember_token' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });

        Schema::dropIfExists('passkeys');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }
};
