<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Seed persists the quiz shuffle seed so archived attempts can reproduce option order.
     */
    public function up(): void
    {
        Schema::table('user_negotiation_situation_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('seed')->nullable()->after('correct_count');
        });

        Schema::table('user_negotiation_final_test_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('seed')->nullable()->after('correct_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_negotiation_situation_attempts', function (Blueprint $table) {
            $table->dropColumn('seed');
        });

        Schema::table('user_negotiation_final_test_attempts', function (Blueprint $table) {
            $table->dropColumn('seed');
        });
    }
};
