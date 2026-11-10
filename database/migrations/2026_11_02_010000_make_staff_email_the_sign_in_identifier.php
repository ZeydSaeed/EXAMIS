<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff sign in by email (ADR-0011), so a staff email must exist and belong
 * to one staff account. Students keep ADR-0002: their emails may repeat, and
 * this index does not touch them. Stops before changing anything when the
 * current data would break either rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('users')->where('account_type', 'staff')->whereNull('email')->count();

        $staffEmails = DB::table('users')->where('account_type', 'staff')->whereNotNull('email');
        $repeated = (clone $staffEmails)->count() - (clone $staffEmails)->distinct()->count(DB::raw('lower(email)'));

        if ($missing > 0 || $repeated > 0) {
            throw new RuntimeException("Fix staff emails first: {$missing} missing, {$repeated} repeated.");
        }

        DB::statement("CREATE UNIQUE INDEX users_staff_email_unique ON users (lower(email)) WHERE account_type = 'staff'");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_staff_email_check CHECK (account_type <> 'staff' OR email IS NOT NULL)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT users_staff_email_check');
        }

        DB::statement('DROP INDEX users_staff_email_unique');
    }
};
