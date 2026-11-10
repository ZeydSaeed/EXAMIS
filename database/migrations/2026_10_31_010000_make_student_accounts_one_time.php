<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time student accounts (ADR-0010): students no longer have a username,
 * several accounts may share a mobile number, and `closed_at` records the end
 * of an account. Staff keep their username, which is how they sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->index('phone');
            $table->string('username', 32)->nullable()->change();
            $table->timestamp('closed_at')->nullable();
        });

        // Registration copied the username into the name, so it goes too.
        DB::table('users')
            ->where('account_type', 'applicant')
            ->whereColumn('name', 'username')
            ->orderBy('id')
            ->get(['id', 'student_number', 'phone'])
            ->each(fn (object $user) => DB::table('users')->where('id', $user->id)->update([
                'name' => (string) ($user->student_number ?? $user->phone ?? $user->id),
            ]));

        DB::table('users')->where('account_type', 'applicant')->update(['username' => null]);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_staff_username_check CHECK (account_type <> 'staff' OR username IS NOT NULL)");
        }
    }

    /**
     * Removed usernames cannot be restored, and the unique mobile number
     * cannot return once accounts share a number.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_staff_username_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn('closed_at');
        });
    }
};
