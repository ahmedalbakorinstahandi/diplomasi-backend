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

        Schema::create('subscription_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->unsignedBigInteger('discount_id');
            $table->foreign('discount_id')->references('id')->on('discount_coupons');
            $table->enum('discount_type', ["fixed","percentage"]);
            $table->decimal('discount_value', 10, 2);
            $table->timestamp('applied_at')->nullable();
            $table->softDeletes();
            $table->unique(['subscription_id', 'discount_id', 'deleted_at'], 'sub_discounts_unique');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};
