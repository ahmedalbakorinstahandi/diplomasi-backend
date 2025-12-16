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
            // add phone_verified and email_verified columns
            $table->boolean('phone_verified')->default(false);
            $table->boolean('email_verified')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // drop phone_verified and email_verified columns
            $table->dropColumn('phone_verified');
            $table->dropColumn('email_verified');
        });
    }
};
