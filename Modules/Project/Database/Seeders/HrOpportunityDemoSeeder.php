<?php

namespace Modules\Project\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskScore;
use Modules\Project\App\Services\ProjectService;
use Modules\Project\App\Services\TaskService;

/**
 * Dữ liệu test theo yêu cầu nghiệp vụ cụ thể (không phải demo tổng quát như
 * ProjectSeeder/TaskSeeder):
 *   1. HRM — dự án phối hợp Hành chính Nhân sự (NS) + Công nghệ (CNTT).
 *   2. Cơ hội bất ngờ — dự án riêng của Hành chính Nhân sự (NS).
 *   3. Quản lý Kho & Bán hàng — dự án riêng của Công nghệ (CNTT).
 *
 * Tasks random hợp lý trong PHẠM VI MEMBER của từng Project:
 *   - Người tạo / người thực hiện / người theo dõi (watcher): bất kỳ member nào.
 *   - Người theo dõi bổ sung (collaborator): member khác người thực hiện.
 *   - Người đánh giá (task_scores.scored_by — "đánh giá tối thiểu" của task,
 *     xem TaskScore): chỉ random trong nhóm member giữ vai trò quản lý
 *     (department_director/deputy_department_director/section_head/team_lead)
 *     hoặc lead_user_id của dự án — đúng quyền phê duyệt/đánh giá công việc.
 *
 * Idempotent: bỏ qua project đã tồn tại theo tên.
 */
class HrOpportunityDemoSeeder extends Seeder
{
    private const MANAGER_ROLE_CODES = [
        'director_officer',
        'department_director',
        'deputy_department_director',
        'section_head',
        'team_lead',
    ];

    private int $codeSeq = 1;

    public function run(): void
    {
        $departments = Department::query()->pluck('id', 'code');
        if ($departments->isEmpty()) {
            $this->command?->warn('Chưa có phòng ban — bỏ qua HrOpportunityDemoSeeder.');

            return;
        }

        $ns = $departments->get('NS');
        $cntt = $departments->get('CNTT');
        if ($ns === null || $cntt === null) {
            $this->command?->warn('Thiếu phòng NS/CNTT — bỏ qua HrOpportunityDemoSeeder.');

            return;
        }

        $nsUsers = User::query()->where('department_id', $ns)->get();
        $cnttUsers = User::query()->where('department_id', $cntt)->get();

        if ($nsUsers->isEmpty() || $cnttUsers->isEmpty()) {
            $this->command?->warn('Thiếu nhân sự NS/CNTT — bỏ qua HrOpportunityDemoSeeder.');

            return;
        }

        $creator = $nsUsers->first();

        $projectService = app(ProjectService::class);
        $taskService = app(TaskService::class);

        // 1. HRM — phối hợp NS + CNTT.
        $hrmMembers = $nsUsers->merge($cnttUsers)->unique('id')->values();
        $hrm = $this->seedProject($projectService, $creator, [
            'type' => 'Nội bộ',
            'name' => 'HRM',
            'lead_user_id' => $creator->id,
            'lead_department_id' => $ns,
            'owner_department_id' => $ns,
            'executing_department_ids' => [$ns, $cntt],
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->addDays(60)->toDateString(),
            'progress_method' => 'task_weighted',
            'status' => 'in_progress',
            'importance' => 'strategic',
            'description' => 'Xây dựng hệ thống quản trị nhân sự nội bộ — Hành chính Nhân sự phối hợp Công nghệ triển khai.',
            'member_ids' => $hrmMembers->pluck('id')->all(),
        ]);

        // 2. Cơ hội bất ngờ — riêng NS.
        $opportunity = $this->seedProject($projectService, $creator, [
            'type' => 'Nội bộ',
            'name' => 'Cơ hội bất ngờ',
            'lead_user_id' => $creator->id,
            'lead_department_id' => $ns,
            'owner_department_id' => $ns,
            'executing_department_ids' => [$ns],
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'progress_method' => 'average',
            'status' => 'in_progress',
            'importance' => 'assist',
            'description' => 'Xử lý các đầu việc phát sinh đột xuất của phòng Hành chính Nhân sự, chưa nằm trong kế hoạch quý.',
            'member_ids' => $nsUsers->pluck('id')->all(),
        ]);

        // 3. Quản lý Kho & Bán hàng — riêng CNTT.
        $cnttCreator = $cnttUsers->first();
        $inventory = $this->seedProject($projectService, $cnttCreator, [
            'type' => 'Nội bộ',
            'name' => 'Quản lý Kho & Bán hàng',
            'lead_user_id' => $cnttUsers->first()->id,
            'lead_department_id' => $cntt,
            'owner_department_id' => $cntt,
            'executing_department_ids' => [$cntt],
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(90)->toDateString(),
            'progress_method' => 'task_weighted',
            'status' => 'in_progress',
            'importance' => 'high_priority',
            'description' => 'Xây dựng phân hệ quản lý kho và bán hàng nội bộ — Phòng Công nghệ thông tin chủ trì.',
            'member_ids' => $cnttUsers->pluck('id')->all(),
        ]);

        if ($hrm !== null) {
            $this->seedTasks($taskService, $hrm, $hrmMembers, [
                ['title' => 'Khảo sát quy trình chấm công & tính lương hiện tại', 'type' => 'phase', 'status' => 'completed', 'priority' => 'strategic', 'progress_percent' => 100, 'days_offset' => [-20, -10]],
                ['title' => 'Thu thập yêu cầu nghiệp vụ phòng Nhân sự', 'status' => 'completed', 'priority' => 'important', 'progress_percent' => 100, 'days_offset' => [-20, -14]],
                ['title' => 'Chốt kiến trúc tích hợp với hệ thống HRM ngoài', 'status' => 'completed', 'priority' => 'high_priority', 'progress_percent' => 100, 'days_offset' => [-14, -10]],
                ['title' => 'Thiết kế & phát triển', 'type' => 'phase', 'status' => 'in_progress', 'priority' => 'strategic', 'progress_percent' => 45, 'days_offset' => [-10, 30]],
                ['title' => 'Xây dựng module hồ sơ nhân viên', 'status' => 'in_progress', 'priority' => 'important', 'progress_percent' => 60, 'days_offset' => [-10, 10]],
                ['title' => 'Xây dựng module chấm công', 'status' => 'in_progress', 'priority' => 'high_priority', 'progress_percent' => 30, 'days_offset' => [-5, 20]],
                ['title' => 'Xây dựng module đánh giá KPI theo bộ tiêu chí HCNS', 'status' => 'not_started', 'priority' => 'strategic', 'progress_percent' => 0, 'days_offset' => [5, 35]],
                ['title' => 'Kiểm thử nghiệm thu với Phòng Hành chính Nhân sự', 'status' => 'not_started', 'priority' => 'high_priority', 'progress_percent' => 0, 'days_offset' => [35, 50]],
                ['title' => 'Đào tạo sử dụng cho nhân viên Nhân sự', 'status' => 'not_started', 'priority' => 'important', 'progress_percent' => 0, 'days_offset' => [50, 60]],
            ]);
        }

        if ($opportunity !== null) {
            $this->seedTasks($taskService, $opportunity, $nsUsers, [
                ['title' => 'Tiếp nhận yêu cầu tuyển dụng gấp thay thế nhân sự nghỉ việc', 'status' => 'in_progress', 'priority' => 'high_priority', 'progress_percent' => 40, 'days_offset' => [-5, 7]],
                ['title' => 'Xử lý khiếu nại lương tháng phát sinh', 'status' => 'completed', 'priority' => 'important', 'progress_percent' => 100, 'days_offset' => [-4, -1]],
                ['title' => 'Chuẩn bị hồ sơ đột xuất theo yêu cầu thanh tra lao động', 'status' => 'in_progress', 'priority' => 'strategic', 'progress_percent' => 55, 'days_offset' => [-3, 10]],
                ['title' => 'Bố trí nhân sự thay ca gấp cho sự kiện phát sinh', 'status' => 'not_started', 'priority' => 'assist', 'progress_percent' => 0, 'days_offset' => [3, 15]],
                ['title' => 'Rà soát và cập nhật quy chế nội bộ theo phản ánh nhân viên', 'status' => 'not_started', 'priority' => 'important', 'progress_percent' => 0, 'days_offset' => [10, 30]],
            ]);
        }

        if ($inventory !== null) {
            $this->seedTasks($taskService, $inventory, $cnttUsers, [
                ['title' => 'Phân tích nghiệp vụ kho & bán hàng', 'type' => 'phase', 'status' => 'completed', 'priority' => 'high_priority', 'progress_percent' => 100, 'days_offset' => [-10, -2]],
                ['title' => 'Khảo sát quy trình nhập/xuất kho hiện tại', 'status' => 'completed', 'priority' => 'important', 'progress_percent' => 100, 'days_offset' => [-10, -5]],
                ['title' => 'Thiết kế mô hình dữ liệu tồn kho', 'status' => 'completed', 'priority' => 'high_priority', 'progress_percent' => 100, 'days_offset' => [-5, -2]],
                ['title' => 'Phát triển phân hệ', 'type' => 'phase', 'status' => 'in_progress', 'priority' => 'strategic', 'progress_percent' => 35, 'days_offset' => [-2, 45]],
                ['title' => 'Module quản lý nhập kho', 'status' => 'in_progress', 'priority' => 'high_priority', 'progress_percent' => 50, 'days_offset' => [-2, 20]],
                ['title' => 'Module bán hàng & xuất hoá đơn', 'status' => 'in_progress', 'priority' => 'strategic', 'progress_percent' => 25, 'days_offset' => [0, 35]],
                ['title' => 'Module báo cáo tồn kho theo thời gian thực', 'status' => 'not_started', 'priority' => 'important', 'progress_percent' => 0, 'days_offset' => [20, 45]],
                ['title' => 'Kiểm thử tích hợp với phân hệ kế toán', 'status' => 'not_started', 'priority' => 'high_priority', 'progress_percent' => 0, 'days_offset' => [45, 70]],
                ['title' => 'Triển khai thử nghiệm tại kho thí điểm', 'status' => 'not_started', 'priority' => 'assist', 'progress_percent' => 0, 'days_offset' => [70, 90]],
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function seedProject(ProjectService $service, User $creator, array $data): ?Project
    {
        $existing = Project::query()->where('name', $data['name'])->first();
        if ($existing !== null) {
            return $existing;
        }

        // ProjectService::create() lấy owner_department_id từ department_id
        // của người tạo — gán tạm trong bộ nhớ (không save) để tái dùng
        // đúng luồng nghiệp vụ thật, giống ProjectSeeder.
        $creator->department_id = $data['owner_department_id'];

        $result = $service->create([
            'type' => $data['type'],
            'name' => $data['name'],
            'lead_user_id' => $data['lead_user_id'],
            'lead_department_id' => $data['lead_department_id'],
            'executing_department_ids' => $data['executing_department_ids'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'progress_method' => $data['progress_method'],
            'status' => $data['status'],
            'importance' => $data['importance'],
            'description' => $data['description'],
            'member_ids' => $data['member_ids'],
        ], $creator);

        return is_array($result) ? null : $result;
    }

    /**
     * @param  Collection<int, User>  $members
     * @param  list<array<string, mixed>>  $tasks
     */
    private function seedTasks(TaskService $service, Project $project, Collection $members, array $tasks): void
    {
        if (Task::query()->where('project_id', $project->id)->exists()) {
            return;
        }

        $memberIds = $members->pluck('id')->values();
        if ($memberIds->isEmpty()) {
            return;
        }

        $managers = $members->filter(fn (User $u) => $u->roles->pluck('code')->intersect(self::MANAGER_ROLE_CODES)->isNotEmpty());
        if ($managers->isEmpty() && $project->lead_user_id !== null) {
            $lead = $members->firstWhere('id', $project->lead_user_id);
            if ($lead !== null) {
                $managers = collect([$lead]);
            }
        }
        if ($managers->isEmpty()) {
            $managers = $members;
        }

        $parentId = null;
        foreach ($tasks as $index => $node) {
            $creator = $this->pick($members);
            $assignee = $this->pick($members);
            $watcherPool = $members->reject(fn (User $u) => $u->id === $assignee->id)->values();
            $watchers = $watcherPool->isEmpty() ? collect() : $watcherPool->random(min(2, $watcherPool->count()));
            $collaboratorPool = $members->reject(fn (User $u) => $u->id === $assignee->id || $watchers->pluck('id')->contains($u->id))->values();
            $collaborators = $collaboratorPool->isEmpty() ? collect() : $collaboratorPool->random(min(1, $collaboratorPool->count()));
            $manager = $this->pick($managers);
            $evaluator = $this->pick($managers);

            [$startOffset, $endOffset] = $node['days_offset'];

            $result = $service->create($project, [
                'parent_id' => ($node['type'] ?? 'task') !== 'phase' ? $parentId : null,
                'type' => $node['type'] ?? 'task',
                'title' => $node['title'],
                'status' => $node['status'],
                'priority' => $node['priority'],
                'start_date' => now()->addDays($startOffset)->toDateString(),
                'end_date' => now()->addDays($endOffset)->toDateString(),
                'assignee_id' => $assignee->id,
                'manager_id' => $manager->id,
                'progress_percent' => $node['progress_percent'],
                'sort_order' => $index,
                'code' => sprintf('CV%04d', $this->codeSeq++),
                'watcher_ids' => $watchers->pluck('id')->all(),
                'collaborator_ids' => $collaborators->pluck('id')->all(),
            ], $creator);

            if (is_array($result)) {
                continue;
            }

            if (($node['type'] ?? 'task') === 'phase') {
                $parentId = $result->id;
            }

            if ($node['status'] === 'completed') {
                TaskScore::query()->updateOrCreate(
                    ['task_id' => $result->id],
                    [
                        'rating_score' => random_int(70, 100),
                        'rating_result' => 'Đạt yêu cầu',
                        'rating_desc' => 'Hoàn thành đúng tiến độ, đạt chất lượng đề ra.',
                        'scored_by' => $evaluator->id,
                        'scored_at' => now()->subDays(max(0, -$endOffset)),
                    ],
                );
            }
        }
    }

    /** @param  Collection<int, User>  $pool */
    private function pick(Collection $pool): User
    {
        return $pool->random();
    }
}
