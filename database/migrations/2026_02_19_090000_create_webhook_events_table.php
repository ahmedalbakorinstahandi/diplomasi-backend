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
        if (Schema::hasTable('webhook_events')) {
            return;
        }

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('moyasar');
            $table->string('payload_id', 191);
            $table->string('event_type', 100)->nullable();
            $table->timestamp('event_created_at')->nullable();
            $table->string('payment_id', 191)->nullable();
            $table->boolean('secret_token_valid')->default(false);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 30)->default('received');
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'payload_id']);
            $table->index(['provider', 'event_type']);
            $table->index(['payment_id']);
            $table->index(['processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
