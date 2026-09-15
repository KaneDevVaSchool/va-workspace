<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;

/**
 * Phòng ban mẫu — TẠM THỜI giả lập cho tới khi có API HRM thật.
 * Có thể XÓA seeder này khi tích hợp API HRM (thay bằng đồng bộ dữ liệu
 * thật từ HRM).
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'CNTT', 'name' => 'Phòng Công nghệ thông tin'],
            ['code' => 'HCNS', 'name' => 'Phòng Hành chính Nhân sự'],
            ['code' => 'KD', 'name' => 'Phòng Kinh doanh'],
        ];

        foreach ($departments as $department) {
            Department::query()->updateOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name'], 'is_active' => true],
            );
        }
    }
}
