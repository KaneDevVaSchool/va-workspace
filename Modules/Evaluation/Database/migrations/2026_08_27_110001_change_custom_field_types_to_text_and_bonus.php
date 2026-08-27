<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trường tùy biến trên Mẫu đánh giá chỉ còn 2 loại:
 *   text  — chữ (nhận xét, ghi chú)
 *   bonus — điểm phụ thêm (cộng ngoài trọng số tiêu chí)
 *
 * Map dữ liệu cũ: number → bonus; select/date → text.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $table = Schema::getConnection()->getTablePrefix().'evaluation_template_custom_fields';

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `field_type` ENUM('text', 'number', 'select', 'date', 'bonus') NOT NULL DEFAULT 'text'");
        }

        DB::table('evaluation_template_custom_fields')
            ->where('field_type', 'number')
            ->update(['field_type' => 'bonus']);

        DB::table('evaluation_template_custom_fields')
            ->whereIn('field_type', ['select', 'date'])
            ->update(['field_type' => 'text']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `field_type` ENUM('text', 'bonus') NOT NULL DEFAULT 'text'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $table = Schema::getConnection()->getTablePrefix().'evaluation_template_custom_fields';

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `field_type` ENUM('text', 'number', 'select', 'date', 'bonus') NOT NULL DEFAULT 'text'");
        }

        DB::table('evaluation_template_custom_fields')
            ->where('field_type', 'bonus')
            ->update(['field_type' => 'number']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `field_type` ENUM('text', 'number', 'select', 'date') NOT NULL DEFAULT 'text'");
        }
    }
};
