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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_period_id')->constrained()->restrictOnDelete();
            $table->string('reference_number', 32)->nullable()->unique();
            $table->uuid('verification_token')->unique();
            $table->enum('status', [
                'draft', 'submitted', 'under_review', 'requires_correction',
                'resubmitted', 'accepted', 'rejected', 'withdrawn',
            ])->default('draft');
            $table->unsignedInteger('lock_version')->default(1);

            $table->string('full_name_ar', 150)->nullable();
            $table->string('full_name_en', 150)->nullable();
            $table->string('mother_name_ar', 150)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('national_id_number', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('governorate_code', 40)->nullable();
            $table->string('directorate_code', 40)->nullable();
            $table->string('school_name', 150)->nullable();
            $table->string('branch_code', 40)->nullable();
            $table->string('category_code', 40)->nullable();
            $table->unsignedSmallInteger('previous_exam_year')->nullable();

            $table->timestamp('declaration_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_period_id']);
            $table->index(['exam_period_id', 'status', 'submitted_at']);
            $table->index(['assigned_to', 'status']);
            $table->index('national_id_number');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE applications ADD CONSTRAINT applications_submitted_fields_check
                CHECK (status = 'draft' OR (reference_number IS NOT NULL AND submitted_at IS NOT NULL AND declaration_accepted_at IS NOT NULL))
                SQL);
            DB::statement('ALTER TABLE applications ADD CONSTRAINT applications_lock_version_check CHECK (lock_version >= 1)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
