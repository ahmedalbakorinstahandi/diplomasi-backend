<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable(false)->change();
        });
    }
};
