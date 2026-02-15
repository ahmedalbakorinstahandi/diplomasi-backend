<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('auto_renew');
            }
            if (!Schema::hasColumn('subscriptions', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('cancel_at_period_end');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'canceled_at')) {
                $table->dropColumn('canceled_at');
            }
            if (Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->dropColumn('cancel_at_period_end');
            }
        });
    }
};
