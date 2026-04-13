<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->string('certificate_template_path', 500)->nullable()->after('has_certificate');
            $table->json('certificate_template_config')->nullable()->after('certificate_template_path');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->string('rendered_name', 255)->nullable()->after('template_path');
            $table->string('rendered_date', 100)->nullable()->after('rendered_name');
            $table->string('template_snapshot_path', 500)->nullable()->after('rendered_date');
            $table->json('template_snapshot_config')->nullable()->after('template_snapshot_path');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE certificates MODIFY image_url VARCHAR(500) NULL');
            DB::statement('ALTER TABLE certificates MODIFY pdf_url VARCHAR(500) NULL');
            DB::statement('ALTER TABLE certificates MODIFY template_path VARCHAR(500) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn(['certificate_template_path', 'certificate_template_config']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn([
                'rendered_name',
                'rendered_date',
                'template_snapshot_path',
                'template_snapshot_config',
            ]);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE certificates MODIFY image_url VARCHAR(255) NULL');
            DB::statement('ALTER TABLE certificates MODIFY pdf_url VARCHAR(255) NULL');
            DB::statement('ALTER TABLE certificates MODIFY template_path VARCHAR(255) NULL');
        }
    }
};
