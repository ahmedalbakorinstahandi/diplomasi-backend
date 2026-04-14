<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_level_certificate_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('level_id');
            $table->boolean('is_eligible')->default(false);
            $table->enum('artifact_status', ['not_generated', 'generated', 'regeneration_needed'])->default('not_generated');
            $table->enum('regeneration_reason', ['generation_failed', 'artifact_missing', 'template_changed', 'certificate_deleted'])->nullable();
            $table->unsignedBigInteger('generated_certificate_id')->nullable();
            $table->timestamp('first_eligible_at')->nullable();
            $table->timestamp('last_eligible_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'level_id'], 'uq_user_level_certificate_eligibility');
            $table->index(['user_id', 'course_id']);
            $table->index(['level_id', 'is_eligible']);
            $table->index(['artifact_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_level_certificate_eligibilities');
    }
};
