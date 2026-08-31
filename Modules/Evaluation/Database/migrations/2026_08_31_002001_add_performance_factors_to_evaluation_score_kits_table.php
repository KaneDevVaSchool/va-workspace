<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cách 2: điểm cơ bản việc + thang tiến độ / chất lượng / xếp loại hiệu suất. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->decimal('task_base_score', 8, 2)->default(100)->after('base_score');
            $table->json('progress_levels')->nullable()->after('weighted_task_levels');
            $table->json('quality_levels')->nullable()->after('progress_levels');
            $table->json('performance_levels')->nullable()->after('quality_levels');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropColumn([
                'task_base_score',
                'progress_levels',
                'quality_levels',
                'performance_levels',
            ]);
        });
    }
};
