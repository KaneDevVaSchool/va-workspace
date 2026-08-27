<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed 8 loại dự án gốc vào project_types (5 loại cũ + 3 loại mới: Nghiên
 * cứu phát triển, Triển khai nghiệm thu, Vận hành cải tiến), rồi chuyển dữ
 * liệu cột `projects.type` từ key enum cũ (internal, customer...) sang đúng
 * tên loại (name) — từ nay `projects.type` là string tự do khớp
 * project_types.name, không còn ràng buộc enum (xem StoreProjectRequest).
 */
return new class extends Migration
{
    private const BASE_TYPES = [
        'internal' => 'Nội bộ',
        'customer' => 'Khách hàng',
        'infrastructure' => 'Hạ tầng',
        'research' => 'Nghiên cứu',
        'other' => 'Khác',
    ];

    private const NEW_TYPES = [
        'Nghiên cứu phát triển',
        'Triển khai nghiệm thu',
        'Vận hành cải tiến',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::BASE_TYPES as $name) {
            DB::table('project_types')->insertOrIgnore([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::NEW_TYPES as $name) {
            DB::table('project_types')->insertOrIgnore([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::BASE_TYPES as $key => $name) {
            DB::table('projects')->where('type', $key)->update(['type' => $name]);
        }
    }

    public function down(): void
    {
        $flipped = array_flip(self::BASE_TYPES);
        foreach ($flipped as $name => $key) {
            DB::table('projects')->where('type', $name)->update(['type' => $key]);
        }

        DB::table('project_types')->whereIn('name', array_merge(array_values(self::BASE_TYPES), self::NEW_TYPES))->delete();
    }
};
