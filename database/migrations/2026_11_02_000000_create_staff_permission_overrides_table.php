<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-staff exceptions to what a role grants: `grant` adds a permission
     * and `deny` removes one. Administrators have none.
     */
    public function up(): void
    {
        Schema::create('staff_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);
            $table->enum('effect', ['grant', 'deny']);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_permission_overrides');
    }
};
