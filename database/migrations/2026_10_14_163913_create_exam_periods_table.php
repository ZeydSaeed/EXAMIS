<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('academic_year', 9);
            $table->enum('status', ['draft', 'open', 'closed', 'archived'])->default('draft');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->json('eligible_branch_codes')->nullable();
            $table->json('eligible_category_codes')->nullable();
            $table->text('instructions_ar')->nullable();
            $table->text('instructions_en')->nullable();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->index(['status', 'opens_at']);
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('description_ar', 500)->nullable();
            $table->string('description_en', 500)->nullable();
            $table->json('allowed_mime_types');
            $table->unsignedInteger('max_size_kb');
            $table->boolean('is_photo')->default(false);
            $table->timestamps();
        });

        Schema::create('document_type_exam_period', function (Blueprint $table) {
            $table->foreignId('exam_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(true);

            $table->primary(['exam_period_id', 'document_type_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exam_periods ADD CONSTRAINT exam_periods_window_check CHECK (closes_at > opens_at)');
            DB::statement('ALTER TABLE document_types ADD CONSTRAINT document_types_max_size_check CHECK (max_size_kb BETWEEN 1 AND 20480)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_type_exam_period');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('exam_periods');
    }
};
