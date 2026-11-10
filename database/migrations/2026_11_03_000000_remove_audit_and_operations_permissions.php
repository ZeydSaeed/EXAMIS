<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * These permissions left the Permission enum with their pages, and a row
     * still naming one would fail the enum cast when the overrides load.
     */
    public function up(): void
    {
        DB::table('staff_permission_overrides')
            ->whereIn('permission', ['audit.view', 'operations.view'])
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
