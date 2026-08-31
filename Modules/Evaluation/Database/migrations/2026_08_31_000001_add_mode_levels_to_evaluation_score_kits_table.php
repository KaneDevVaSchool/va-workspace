<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mỗi cách tính có thang 5 mức riêng, phòng tự sửa (không lấy từ tiêu chí đánh giá).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->json('base_adjust_levels')->nullable()->after('classification_criterion_id');
            $table->json('weighted_task_levels')->nullable()->after('base_adjust_levels');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropColumn(['base_adjust_levels', 'weighted_task_levels']);
        });
    }
};
