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
        Schema::disableForeignKeyConstraints();

        Schema::create('ai_negotiator_user_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'ain_credits_user_fk')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->unsignedInteger('credit_balance')->default(0);
            $table->unsignedInteger('consumed_this_cycle')->default(0);
            // dateTime (not timestamp): MySQL/MariaDB rejects a second NOT NULL timestamp
            // without an explicit default under strict mode (1067 Invalid default value).
            $table->dateTime('cycle_started_at');
            $table->dateTime('cycle_ends_at');
            $table->timestamp('last_refilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'deleted_at'], 'ain_credits_user_uq');
            $table->index(['cycle_ends_at'], 'ain_credits_cycle_end_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_user_credits');
    }
};
