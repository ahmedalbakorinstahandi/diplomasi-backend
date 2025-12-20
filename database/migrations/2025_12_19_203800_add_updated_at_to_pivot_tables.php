<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('role_permissions', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('user_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('user_roles', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('role_permissions', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });

        Schema::table('user_roles', function (Blueprint $table) {
            if (Schema::hasColumn('user_roles', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
