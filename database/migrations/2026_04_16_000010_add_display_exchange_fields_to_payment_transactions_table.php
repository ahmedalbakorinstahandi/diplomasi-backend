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
            if (!Schema::hasColumn('payment_transactions', 'display_currency')) {
                $table->string('display_currency', 3)->nullable()->after('currency');
            }

            if (!Schema::hasColumn('payment_transactions', 'display_amount_minor')) {
                $table->unsignedBigInteger('display_amount_minor')->nullable()->after('display_currency');
            }

            if (!Schema::hasColumn('payment_transactions', 'exchange_rate_usd_to_sar')) {
                $table->decimal('exchange_rate_usd_to_sar', 20, 10)->nullable()->after('display_amount_minor');
            }

            if (!Schema::hasColumn('payment_transactions', 'exchange_rate_at')) {
                $table->timestamp('exchange_rate_at')->nullable()->after('exchange_rate_usd_to_sar');
            }

            if (!Schema::hasColumn('payment_transactions', 'exchange_rate_source')) {
                $table->string('exchange_rate_source', 50)->nullable()->after('exchange_rate_at');
            }

            if (!Schema::hasColumn('payment_transactions', 'disclaimer_version')) {
                $table->string('disclaimer_version', 50)->nullable()->after('exchange_rate_source');
            }

            if (!Schema::hasColumn('payment_transactions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('disclaimer_version');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'display_currency',
                'display_amount_minor',
                'exchange_rate_usd_to_sar',
                'exchange_rate_at',
                'exchange_rate_source',
                'disclaimer_version',
                'expires_at',
            ]);
        });
    }
};

