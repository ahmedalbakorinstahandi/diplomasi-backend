<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-add payment_attempts table and Geidea fields on subscriptions.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (!Schema::hasTable('payment_attempts')) {
            Schema::create('payment_attempts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unsignedBigInteger('plan_id');
                $table->foreign('plan_id')->references('id')->on('plans')->onDelete('restrict');
                $table->string('merchant_reference', 255)->unique();
                $table->string('geidea_session_id', 255)->nullable();
                $table->string('geidea_order_id', 255)->nullable();
                $table->string('geidea_subscription_id', 255)->nullable();
                $table->text('checkout_url')->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->enum('status', ['initiated', 'pending', 'verifying', 'completed', 'failed', 'canceled', 'expired'])->default('initiated');
                $table->text('failure_reason')->nullable();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['merchant_reference']);
                $table->index(['user_id', 'status']);
                $table->index(['status', 'created_at']);
                $table->index(['geidea_order_id']);
                $table->index(['subscription_id']);
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (!Schema::hasColumn('subscriptions', 'geidea_subscription_id')) {
                    $table->string('geidea_subscription_id', 255)->nullable()->after('auto_renew');
                }
                if (!Schema::hasColumn('subscriptions', 'geidea_order_id')) {
                    $table->string('geidea_order_id', 255)->nullable()->after('geidea_subscription_id');
                }
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('payment_attempts');
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'geidea_subscription_id')) {
                    $table->dropColumn('geidea_subscription_id');
                }
                if (Schema::hasColumn('subscriptions', 'geidea_order_id')) {
                    $table->dropColumn('geidea_order_id');
                }
            });
        }
        Schema::enableForeignKeyConstraints();
    }
};
