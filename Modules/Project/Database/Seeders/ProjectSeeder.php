<?php

namespace Modules\Project\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Services\ProjectService;

/**
 * Dự án demo — vài dự án mẫu trải đều nhiều phòng ban thực hiện, nhiều
 * trạng thái/mức độ quan trọng, để xem được giao diện list (group theo
 * phòng ban thực hiện, nhãn, panel chi tiết...) có dữ liệu thật ngay.
 *
 * Idempotent: updateOrCreate theo `name` (không có unique code cố định
 * trước — code sinh tự động qua ProjectService::create() giống app thật).
 * Bỏ qua nếu department/user demo chưa được seed (DepartmentSeeder,
 * DemoUserSeeder phải chạy trước — xem DatabaseSeeder::run()).
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->orderBy('id')->first();
        if ($creator === null) {
            return;
        }

        $departments = Department::query()->pluck('id', 'code');
        if ($departments->isEmpty()) {
            return;
        }

        $labels = $this->seedLabels($creator->id);

        $service = app(ProjectService::class);

        $samples = [
            [
                'name' => 'Nâng cấp hạ tầng mạng nội bộ',
                'type' => 'infrastructure',
                'owner_code' => 'CNTT',
                'executing_code' => 'CNTT',
                'status' => 'in_progress',
                'importance' => 'high',
                'progress_method' => 'duration_weighted',
                'start_date' => now()->subDays(20)->toDateString(),
                'end_date' => now()->addDays(40)->toDateString(),
                'description' => 'Thay thế switch, nâng băng thông đường truyền cho toàn bộ các phòng ban.',
                'labels' => ['Ưu tiên', 'Hạ tầng'],
            ],
            [
                'name' => 'Triển khai hệ thống quản lý đào tạo trực tuyến',
                'type' => 'internal',
                'owner_code' => 'DT',
                'executing_code' => 'CNTT',
                'status' => 'in_progress',
                'importance' => 'critical',
                'progress_method' => 'task_weighted',
                'start_date' => now()->subDays(45)->toDateString(),
                'end_date' => now()->addDays(15)->toDateString(),
                'description' => 'Ban Giám hiệu giao Phòng CNTT xây dựng cổng đào tạo trực tuyến cho toàn trường.',
                'labels' => ['Ưu tiên'],
            ],
            [
                'name' => 'Số hoá hồ sơ nhân sự',
                'type' => 'internal',
                'owner_code' => 'NS',
                'executing_code' => null,
                'status' => 'planning',
                'importance' => 'medium',
                'progress_method' => 'average',
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(70)->toDateString(),
                'description' => 'Chuyển toàn bộ hồ sơ giấy sang lưu trữ điện tử, có phân quyền truy cập.',
                'labels' => ['Nội bộ'],
            ],
            [
                'name' => 'Kiểm toán tài chính năm học 2025-2026',
                'type' => 'internal',
                'owner_code' => 'TC',
                'executing_code' => null,
                'status' => 'completed',
                'importance' => 'high',
                'progress_method' => 'average',
                'start_date' => now()->subDays(90)->toDateString(),
                'end_date' => now()->subDays(10)->toDateString(),
                'description' => 'Soát xét sổ sách, đối chiếu công nợ và lập báo cáo kiểm toán nội bộ.',
                'labels' => [],
            ],
            [
                'name' => 'Xây dựng chương trình khách hàng thân thiết',
                'type' => 'customer',
                'owner_code' => 'BGH',
                'executing_code' => 'DT',
                'status' => 'on_hold',
                'importance' => 'low',
                'progress_method' => 'average',
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(60)->toDateString(),
                'description' => 'Tạm dừng chờ phê duyệt ngân sách marketing quý tới.',
                'labels' => ['Khách hàng'],
            ],
            [
                'name' => 'Nghiên cứu ứng dụng AI hỗ trợ giảng dạy',
                'type' => 'research',
                'owner_code' => 'CNTT',
                'executing_code' => null,
                'status' => 'cancelled',
                'importance' => 'low',
                'progress_method' => 'average',
                'start_date' => now()->subDays(60)->toDateString(),
                'end_date' => now()->subDays(30)->toDateString(),
                'description' => 'Huỷ do trùng phạm vi với dự án đào tạo trực tuyến.',
                'labels' => [],
            ],
        ];

        foreach ($samples as $sample) {
            $this->seedProject($service, $creator, $departments, $labels, $sample);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $departments  code => id
     * @param  array<string, int>  $labels  tên nhãn => id
     * @param  array<string, mixed>  $sample
     */
    private function seedProject(
        ProjectService $service,
        User $creator,
        $departments,
        array $labels,
        array $sample,
    ): void {
        if (Project::query()->where('name', $sample['name'])->exists()) {
            return;
        }

        $ownerDepartmentId = $departments->get($sample['owner_code']);
        if ($ownerDepartmentId === null) {
            return;
        }

        // ProjectService::create() lấy owner_department_id từ department_id của
        // người tạo — tạm gán department_id cho $creator trong bộ nhớ (không
        // save xuống DB) để tái dùng đúng luồng nghiệp vụ thật, không phải
        // set cứng sai người tạo demo.
        $creator->department_id = $ownerDepartmentId;

        $executingDepartmentId = $sample['executing_code'] ? $departments->get($sample['executing_code']) : null;

        $labelIds = collect($sample['labels'])
            ->map(fn (string $name) => $labels[$name] ?? null)
            ->filter()
            ->values()
            ->all();

        $result = $service->create([
            'type' => $sample['type'],
            'name' => $sample['name'],
            'executing_department_id' => $executingDepartmentId,
            'start_date' => $sample['start_date'],
            'end_date' => $sample['end_date'],
            'progress_method' => $sample['progress_method'],
            'status' => $sample['status'],
            'importance' => $sample['importance'],
            'description' => $sample['description'],
            'member_ids' => [],
            'scopes' => [],
            'label_ids' => $labelIds,
        ], $creator);

        if (is_array($result)) {
            return;
        }

        if ($sample['status'] === 'completed') {
            $result->update(['evaluation_score' => 8.5]);
        }
    }

    /** @return array<string, int> tên nhãn => id */
    private function seedLabels(int $createdBy): array
    {
        $labels = [
            'Ưu tiên' => 'danger',
            'Hạ tầng' => 'info',
            'Nội bộ' => 'primary',
            'Khách hàng' => 'warning',
        ];

        $ids = [];
        foreach ($labels as $name => $color) {
            $label = ProjectLabel::query()->firstOrCreate(
                ['name' => $name],
                ['color' => $color, 'created_by' => $createdBy],
            );
            $ids[$name] = $label->id;
        }

        return $ids;
    }
}
