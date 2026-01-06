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
        Schema::table('user_scenario_attempts', function (Blueprint $table) {
            $table->boolean('description_read')->default(false)->after('status');
            $table->timestamp('description_read_at')->nullable()->after('description_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_scenario_attempts', function (Blueprint $table) {
            $table->dropColumn(['description_read', 'description_read_at']);
        });
    }
};
