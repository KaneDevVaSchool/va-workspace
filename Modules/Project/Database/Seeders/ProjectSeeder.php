<?php

namespace Modules\Project\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Services\ProjectService;

/**
 * Dự án demo — trải đều nhiều phòng ban thực hiện, nhiều trạng thái/mức độ
 * quan trọng, để xem được giao diện list (group theo phòng ban thực hiện,
 * nhãn, avatar người tham gia, panel chi tiết...) có dữ liệu thật ngay.
 *
 * Cố tình bao phủ đủ các quan hệ phòng ban ↔ dự án ↔ người tham gia có thể
 * gặp trong thực tế (mỗi sample dưới đây chú thích rõ đang minh hoạ trường
 * hợp nào):
 *   1. Phòng A giao Phòng B thực hiện toàn bộ (owner ≠ executing).
 *   2. Nhiều phòng ban cùng thực hiện 1 dự án (pivot executing_departments).
 *   3. Dự án riêng nội bộ của 1 phòng ban (owner = executing, không scope khác).
 *   4. Dự án của 1 phòng ban nhưng có người tham gia ở phòng ban khác (member
 *      không thuộc owner/executing department).
 *   5. Dự án có nhiều người tham gia (>3 — để xem avatar-stack "+N" trên list).
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

        $users = User::query()->get()->keyBy('email');

        $labels = $this->seedLabels($creator->id);

        $service = app(ProjectService::class);

        $samples = [
            // 1. Phòng A (BGH) giao Phòng B (CNTT) thực hiện toàn bộ.
            [
                'name' => 'Triển khai hệ thống quản lý đào tạo trực tuyến',
                'type' => 'Nội bộ',
                'owner_code' => 'BGH',
                'executing_code' => 'CNTT',
                'status' => 'in_progress',
                'importance' => 'strategic',
                'progress_method' => 'task_weighted',
                'start_date' => now()->subDays(45)->toDateString(),
                'end_date' => now()->addDays(15)->toDateString(),
                'description' => 'Ban Giám hiệu giao Phòng CNTT xây dựng cổng đào tạo trực tuyến cho toàn trường.',
                'labels' => ['Ưu tiên'],
                'member_emails' => ['duc.cntt@example.com', 'linh.cntt@example.com'],
                'lead_email' => 'duc.cntt@example.com',
            ],

            // 2. Nhiều phòng ban cùng thực hiện, phạm vi Toàn Hệ Thống.
            [
                'name' => 'Nâng cấp hạ tầng mạng nội bộ',
                'type' => 'Hạ tầng',
                'owner_code' => 'CNTT',
                'executing_codes' => ['CNTT', 'DT', 'NS'],
                'status' => 'in_progress',
                'importance' => 'high_priority',
                'progress_method' => 'duration_weighted',
                'start_date' => now()->subDays(20)->toDateString(),
                'end_date' => now()->addDays(40)->toDateString(),
                'description' => 'Thay thế switch, nâng băng thông đường truyền cho toàn bộ các phòng ban — CNTT chủ trì, Đào tạo và Hành chính Nhân sự phối hợp bố trí thời gian cắt mạng theo khu vực.',
                'labels' => ['Ưu tiên', 'Hạ tầng'],
                'member_emails' => ['duc.cntt@example.com', 'linh.cntt@example.com', 'tuan.dt@example.com', 'son.ns@example.com'],
                'lead_email' => 'duc.cntt@example.com',
                'scopes' => [
                    ['scope_type' => 'ht', 'weight_percent' => 100],
                ],
            ],

            // 3. Dự án riêng nội bộ 1 phòng ban (owner = executing, không giao/không multi-scope).
            [
                'name' => 'Số hoá hồ sơ nhân sự',
                'type' => 'Nội bộ',
                'owner_code' => 'NS',
                'executing_code' => 'NS',
                'status' => 'planning',
                'importance' => 'important',
                'progress_method' => 'average',
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(70)->toDateString(),
                'description' => 'Chuyển toàn bộ hồ sơ giấy sang lưu trữ điện tử, có phân quyền truy cập. Phòng Hành chính Nhân sự tự thực hiện, không giao phòng ban khác.',
                'labels' => ['Nội bộ'],
                'member_emails' => ['son.ns@example.com', 'thao.ns@example.com'],
                'lead_email' => 'son.ns@example.com',
            ],

            // 4. Dự án của Phòng Tài chính nhưng có người tham gia ở phòng ban khác
            //    (CNTT hỗ trợ trích xuất dữ liệu, DT đối chiếu số liệu đào tạo).
            [
                'name' => 'Kiểm toán tài chính năm học 2025-2026',
                'type' => 'Nội bộ',
                'owner_code' => 'TC',
                'executing_code' => 'TC',
                'status' => 'completed',
                'importance' => 'high_priority',
                'progress_method' => 'average',
                'start_date' => now()->subDays(90)->toDateString(),
                'end_date' => now()->subDays(10)->toDateString(),
                'description' => 'Soát xét sổ sách, đối chiếu công nợ và lập báo cáo kiểm toán nội bộ. Có nhờ CNTT trích xuất dữ liệu hệ thống và Đào tạo đối chiếu số liệu học phí.',
                'labels' => [],
                'member_emails' => ['phong.tc@example.com', 'yen.tc@example.com', 'duc.cntt@example.com', 'tuan.dt@example.com'],
                'lead_email' => 'phong.tc@example.com',
                'evaluation_score' => 8.5,
            ],

            // 5. Dự án có nhiều người tham gia (>3 — test avatar-stack "+N").
            [
                'name' => 'Ngày hội tuyển sinh 2026',
                'type' => 'Khách hàng',
                'owner_code' => 'BGH',
                'executing_code' => 'DT',
                'status' => 'in_progress',
                'importance' => 'high_priority',
                'progress_method' => 'task_weighted',
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'description' => 'Tổ chức ngày hội tuyển sinh — huy động nhân sự nhiều phòng ban cùng tham gia đón tiếp, tư vấn, hậu cần.',
                'labels' => ['Khách hàng'],
                'member_emails' => [
                    'tuan.dt@example.com', 'mai.dt@example.com',
                    'duc.cntt@example.com', 'linh.cntt@example.com',
                    'son.ns@example.com', 'thao.ns@example.com',
                    'hoa.bgh@example.com',
                ],
                'lead_email' => 'tuan.dt@example.com',
            ],

            // Thêm 2 mẫu phụ để trải đủ trạng thái/tab lọc trên list.
            [
                'name' => 'Xây dựng chương trình khách hàng thân thiết',
                'type' => 'Khách hàng',
                'owner_code' => 'BGH',
                'executing_code' => 'DT',
                'status' => 'on_hold',
                'importance' => 'support',
                'progress_method' => 'average',
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(60)->toDateString(),
                'description' => 'Tạm dừng chờ phê duyệt ngân sách marketing quý tới.',
                'labels' => ['Khách hàng'],
                'member_emails' => ['mai.dt@example.com'],
            ],
            [
                'name' => 'Nghiên cứu ứng dụng AI hỗ trợ giảng dạy',
                'type' => 'Nghiên cứu',
                'owner_code' => 'CNTT',
                'executing_code' => 'CNTT',
                'status' => 'cancelled',
                'importance' => 'support',
                'progress_method' => 'average',
                'start_date' => now()->subDays(60)->toDateString(),
                'end_date' => now()->subDays(30)->toDateString(),
                'description' => 'Huỷ do trùng phạm vi với dự án đào tạo trực tuyến.',
                'labels' => [],
                'member_emails' => [],
            ],
        ];

        foreach ($samples as $sample) {
            $this->seedProject($service, $creator, $departments, $users, $labels, $sample);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $departments  code => id
     * @param  \Illuminate\Support\Collection<string, User>  $users  email => User
     * @param  array<string, int>  $labels  tên nhãn => id
     * @param  array<string, mixed>  $sample
     */
    private function seedProject(
        ProjectService $service,
        User $creator,
        $departments,
        $users,
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

        $executingCodes = $sample['executing_codes'] ?? (isset($sample['executing_code']) ? [$sample['executing_code']] : []);
        $executingDepartmentIds = collect($executingCodes)
            ->map(fn ($code) => $departments->get($code))
            ->filter()
            ->values()
            ->all();
        $leadUser = isset($sample['lead_email']) ? $users->get($sample['lead_email']) : null;
        $leadDepartmentId = $leadUser?->department_id;

        $labelIds = collect($sample['labels'])
            ->map(fn (string $name) => $labels[$name] ?? null)
            ->filter()
            ->values()
            ->all();

        $memberIds = collect($sample['member_emails'] ?? [])
            ->map(fn (string $email) => $users->get($email)?->id)
            ->filter()
            ->values()
            ->all();

        $leadUserId = $leadUser?->id;

        $scopes = collect($sample['scopes'] ?? [])
            ->map(function (array $scope) use ($departments) {
                $row = ['scope_type' => $scope['scope_type'], 'weight_percent' => $scope['weight_percent'] ?? 0];
                if (($scope['scope_type'] ?? null) === 'department') {
                    $departmentId = $departments->get($scope['department_code'] ?? null);
                    if ($departmentId === null) {
                        return null;
                    }
                    $row['department_id'] = $departmentId;
                }

                return $row;
            })
            ->filter()
            ->values()
            ->all();

        $result = $service->create([
            'type' => $sample['type'],
            'name' => $sample['name'],
            'lead_user_id' => $leadUserId,
            'lead_department_id' => $leadDepartmentId,
            'executing_department_ids' => $executingDepartmentIds,
            'start_date' => $sample['start_date'],
            'end_date' => $sample['end_date'],
            'progress_method' => $sample['progress_method'],
            'status' => $sample['status'],
            'importance' => $sample['importance'],
            'description' => $sample['description'],
            'member_ids' => $memberIds,
            'scopes' => $scopes,
            'label_ids' => $labelIds,
        ], $creator);

        if (is_array($result)) {
            return;
        }

        if (isset($sample['evaluation_score'])) {
            $result->update(['evaluation_score' => $sample['evaluation_score']]);
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
