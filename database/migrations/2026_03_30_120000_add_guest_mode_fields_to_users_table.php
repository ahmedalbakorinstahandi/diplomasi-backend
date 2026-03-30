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
            $table->boolean('is_guest')->default(false)->after('status');
            $table->timestamp('guest_converted_at')->nullable()->after('is_guest');
            $table->timestamp('registration_completed_at')->nullable()->after('guest_converted_at');
            $table->timestamp('guest_last_active_at')->nullable()->after('registration_completed_at');

            $table->index('is_guest');

            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_guest']);
            $table->dropColumn([
                'is_guest',
                'guest_converted_at',
                'registration_completed_at',
                'guest_last_active_at',
            ]);

            $table->string('email')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
