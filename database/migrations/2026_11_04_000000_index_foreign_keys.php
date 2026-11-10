<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL does not index foreign keys by itself. Without these, every
     * check that a referenced row is still in use (RESTRICT, SET NULL on
     * delete) scans the whole child table while holding locks.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'application_documents' => ['document_type_id', 'uploaded_by'],
        'document_type_exam_period' => ['document_type_id'],
        'role_assignments' => ['granted_by'],
        'content_pages' => ['updated_by'],
        'staff_permission_overrides' => ['granted_by'],
        'message_templates' => ['updated_by'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
