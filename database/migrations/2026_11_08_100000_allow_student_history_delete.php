<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * History stays append-only, with one exception: deleting a student from the
 * staff database screen (DeleteStudent) may remove that student's application
 * history, and only inside a transaction that set
 * examis.allow_history_delete. The audit trail has no exception.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION examis_reject_history_mutation() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE'
                    AND TG_TABLE_NAME = 'application_events'
                    AND current_setting('examis.allow_history_delete', true) = 'on' THEN
                    RETURN OLD;
                END IF;
                RAISE EXCEPTION 'History table % is append-only', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION examis_reject_history_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'History table % is append-only', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
    }
};
