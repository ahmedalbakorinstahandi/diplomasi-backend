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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('otp_expire_at');
            $table->timestamp('last_opened_app_at')->nullable()->after('last_activity_at');
            $table->boolean('is_active')->default(false)->after('last_opened_app_at');
            $table->timestamp('inactive_since_at')->nullable()->after('is_active');

            $table->index('last_activity_at');
            $table->index('last_opened_app_at');
            $table->index('is_active');
            $table->index('inactive_since_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['last_opened_app_at']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['inactive_since_at']);

            $table->dropColumn([
                'last_activity_at',
                'last_opened_app_at',
                'is_active',
                'inactive_since_at',
            ]);
        });
    }
};
