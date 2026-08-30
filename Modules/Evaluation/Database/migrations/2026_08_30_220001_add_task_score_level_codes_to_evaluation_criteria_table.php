<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Các mã mức (level.code, hoặc #index nếu không có mã) được gói vào
 * khung chấm điểm theo công việc của phòng ban. Một tiêu chí có thể
 * gói một phần thang điểm — form chấm task sau này bốc từ khung này.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_criteria', 'task_score_level_codes')) {
                $table->json('task_score_level_codes')->nullable()->after('use_for_task_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_criteria', 'task_score_level_codes')) {
                $table->dropColumn('task_score_level_codes');
            }
        });
    }
};
