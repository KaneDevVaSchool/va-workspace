<?php

namespace Modules\Evaluation\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Evaluation\App\Models\EvaluationPosition;
use Modules\Identity\App\Models\Department;

/**
 * "Vị trí đánh giá" mẫu — vài chức danh (kind=position) cố định để demo, cộng
 * với toàn bộ phòng ban hiện có (kind=department) để có thể đánh giá theo cả
 * phòng ban. Idempotent: updateOrCreate theo name (đã unique ở DB).
 *
 * Danh mục này tự quản trong Workspace ở giai đoạn hiện tại — hrm_position_uuid
 * để trống, chờ VA-HRM có API thật (xem memory hrm-employee-sync-future).
 */
class EvaluationPositionSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::query()->orderBy('id')->value('id');

        foreach ($this->positions() as $position) {
            EvaluationPosition::query()->updateOrCreate(
                ['name' => $position['name']],
                [
                    'kind'        => EvaluationPosition::KIND_POSITION,
                    'description' => $position['description'],
                    'created_by'  => $createdBy,
                ],
            );
        }

        $departments = Department::query()->orderBy('id')->get();
        foreach ($departments as $department) {
            EvaluationPosition::query()->updateOrCreate(
                ['name' => $department->name],
                [
                    'kind'        => EvaluationPosition::KIND_DEPARTMENT,
                    'description' => 'Đánh giá áp dụng cho toàn bộ phòng '.$department->name.'.',
                    'created_by'  => $createdBy,
                ],
            );
        }
    }

    /** @return list<array{name: string, description: string}> */
    private function positions(): array
    {
        return [
            [
                'name'        => 'Trưởng phòng Marketing',
                'description' => 'Chức danh quản lý phòng Marketing.',
            ],
            [
                'name'        => 'Trưởng nhóm Kế toán',
                'description' => 'Chức danh quản lý nhóm trong phòng Kế toán.',
            ],
            [
                'name'        => 'Nhân viên Kinh doanh',
                'description' => 'Chức danh chuyên viên kinh doanh.',
            ],
        ];
    }
}
