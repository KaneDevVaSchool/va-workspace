<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_passed` — kết quả Đạt/Không đạt tường minh (khác `rating_result` vốn
 * là text tự do, không enum — xem docblock TaskScore.php). Khi lưu
 * is_passed = false, TaskScoreService::upsert() tăng
 * tasks.failed_review_count +1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('task_scores', 'is_passed')) {
                $table->boolean('is_passed')->nullable()->after('rating_result');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_scores', function (Blueprint $table) {
            $table->dropColumn('is_passed');
        });
    }
};
