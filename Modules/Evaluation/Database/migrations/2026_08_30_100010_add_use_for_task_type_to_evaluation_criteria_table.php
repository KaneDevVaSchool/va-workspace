<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi phòng ban gán tối đa 1 tiêu chí thang điểm làm nguồn
 * "loại công việc / mức độ quan trọng" trên form tạo việc.
 * Exclusive được giữ ở tầng Service (bật cái này thì tắt các tiêu chí khác
 * cùng phòng), không dùng unique index vì nhiều dòng false cùng lúc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_criteria', 'use_for_task_type')) {
                $table->boolean('use_for_task_type')->default(false)->after('use_in_evaluation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_criteria', 'use_for_task_type')) {
                $table->dropColumn('use_for_task_type');
            }
        });
    }
};
