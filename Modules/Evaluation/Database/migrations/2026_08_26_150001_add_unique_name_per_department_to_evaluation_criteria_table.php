<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chặn trùng tên tiêu chí trong cùng phòng ban ở tầng DB (bổ sung cho validate
 * ở StoreEvaluationCriteriaRequest/UpdateEvaluationCriteriaRequest).
 *
 * Trước khi thêm unique index, dọn các bản ghi đã trùng tên sẵn có (không phân
 * biệt hoa/thường, đã trim) — chỉ giữ bản ghi id nhỏ nhất (tạo sớm nhất) của
 * mỗi nhóm trùng, xoá các bản sao còn lại — nếu không migration sẽ báo lỗi
 * "Duplicate entry" khi add index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('evaluation_criteria')
            ->selectRaw('department_id, LOWER(TRIM(name)) as normalized_name')
            ->groupBy('department_id', DB::raw('LOWER(TRIM(name))'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $ids = DB::table('evaluation_criteria')
                ->where('department_id', $group->department_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$group->normalized_name])
                ->orderBy('id')
                ->pluck('id');

            // Giữ bản ghi đầu tiên (id nhỏ nhất), xoá các bản sao còn lại.
            $idsToDelete = $ids->slice(1)->all();
            if (count($idsToDelete) > 0) {
                DB::table('evaluation_criteria')->whereIn('id', $idsToDelete)->delete();
            }
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->unique(['department_id', 'name'], 'evaluation_criteria_department_id_name_unique');
        });
    }

    public function down(): void
    {
        // MySQL/InnoDB có thể đã bỏ index đơn department_id (tự sinh cho FK
        // ở migration tạo bảng) vì thấy unique index composite
        // (department_id, name) này đã "che" được truy vấn theo department_id
        // — khi đó FK dùng luôn unique index này làm supporting index, và
        // dropUnique() sẽ báo lỗi 1553 "needed in a foreign key constraint".
        // Thêm lại index đơn trước để FK có chỗ bám, rồi mới drop unique.
        // Kiểm tra tồn tại trước khi thêm (idempotent) — tránh lỗi "Duplicate
        // key name" nếu down() từng chạy dở rồi chạy lại. Dùng getTablePrefix()
        // vì SHOW INDEX cần tên bảng đã gắn prefix (va_workspace_...), khác
        // với Schema::table() ở trên tự thêm prefix hộ.
        $prefix = DB::getTablePrefix();
        $indexExists = DB::select(
            "SHOW INDEX FROM `{$prefix}evaluation_criteria` WHERE Key_name = ?",
            ['evaluation_criteria_department_id_index'],
        );

        if ($indexExists === []) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->index('department_id', 'evaluation_criteria_department_id_index');
            });
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropUnique('evaluation_criteria_department_id_name_unique');
        });
    }
};
