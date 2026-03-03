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

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->enum('event_type', ["created","renewed","upgraded","downgraded","cancelled","expired","status_changed"]);
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->enum('status', ["active","cancelled","expired","past_due"]);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('plan_price', 10, 2)->nullable();
            $table->decimal('amount_charged', 10, 2)->nullable();
            $table->decimal('amount_refunded', 10, 2)->nullable();
            $table->string('currency', 10)->nullable()->default('USD');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->softDeletes();
            $table->index(['subscription_id', 'deleted_at', 'created_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
