<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Công việc không bắt buộc thuộc dự án — để trống project_id là công việc
 * thường xuyên (không gắn WBS của một dự án).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        $prefixed = Schema::getConnection()->getTablePrefix().'tasks';
        DB::statement("ALTER TABLE `{$prefixed}` MODIFY `project_id` BIGINT UNSIGNED NULL");

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        $prefixed = Schema::getConnection()->getTablePrefix().'tasks';
        DB::statement("ALTER TABLE `{$prefixed}` MODIFY `project_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
        });
    }
};
