<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Superadmin tạo mẫu dùng chung toàn hệ thống mà không thuộc phòng ban
 * nào — `department_id` được phép null khi `is_global = true`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_templates', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        $table = Schema::getConnection()->getTablePrefix().'evaluation_templates';
        DB::statement("ALTER TABLE `{$table}` MODIFY `department_id` BIGINT UNSIGNED NULL");

        Schema::table('evaluation_templates', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('evaluation_templates')->whereNull('department_id')->delete();

        Schema::table('evaluation_templates', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });

        $table = Schema::getConnection()->getTablePrefix().'evaluation_templates';
        DB::statement("ALTER TABLE `{$table}` MODIFY `department_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('evaluation_templates', function (Blueprint $table) {
            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->cascadeOnDelete();
        });
    }
};
