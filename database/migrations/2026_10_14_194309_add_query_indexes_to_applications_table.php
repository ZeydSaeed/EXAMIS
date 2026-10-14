<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes justified by EXPLAIN ANALYZE on 100k synthetic applications
     * (docs/database/SCHEMA.md §3):
     * - queue order: the unfiltered review queue sorted a parallel seq scan
     *   (≈190 ms); the partial index serves ORDER BY submitted_at, id directly.
     * - name prefix: `full_name_ar LIKE 'x%'` inside the staff search OR
     *   forced a seq scan (≈95 ms); text_pattern_ops makes every branch indexed.
     */
    public function up(): void
    {
        DB::statement("CREATE INDEX applications_queue_order_index ON applications (submitted_at, id) WHERE status <> 'draft'");

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX applications_full_name_ar_prefix_index ON applications (full_name_ar text_pattern_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS applications_queue_order_index');
        DB::statement('DROP INDEX IF EXISTS applications_full_name_ar_prefix_index');
    }
};
