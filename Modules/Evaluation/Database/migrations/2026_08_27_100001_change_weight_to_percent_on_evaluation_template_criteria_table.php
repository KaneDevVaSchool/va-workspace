<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đổi trọng số tiêu chí trong Mẫu đánh giá từ nhãn định tính
 * (weight_label: khong/kha/rat_quan_trong + weight_value ẩn 1-4) sang
 * phần trăm (weight_percent, 10-100, tổng các dòng trong 1 mẫu = 100%,
 * validate ở StoreEvaluationTemplateRequest/UpdateEvaluationTemplateRequest).
 *
 * weight_value chưa từng được dùng để tính điểm ở đâu (phiếu đánh giá thật —
 * Giai đoạn D — chưa dựng), nên xoá thẳng thay vì giữ song song 2 cột.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_template_criteria', function (Blueprint $table) {
            $table->dropColumn(['weight_label', 'weight_value']);
        });

        Schema::table('evaluation_template_criteria', function (Blueprint $table) {
            $table->unsignedTinyInteger('weight_percent')->default(10)->after('evaluation_criteria_id');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_template_criteria', function (Blueprint $table) {
            $table->dropColumn('weight_percent');
        });

        Schema::table('evaluation_template_criteria', function (Blueprint $table) {
            $table->enum('weight_label', [
                'khong_quan_trong',
                'quan_trong',
                'kha_quan_trong',
                'rat_quan_trong',
            ])->default('quan_trong')->after('evaluation_criteria_id');
            $table->unsignedTinyInteger('weight_value')->default(2)->after('weight_label');
        });
    }
};
