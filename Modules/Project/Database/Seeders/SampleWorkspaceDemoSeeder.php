<?php

namespace Modules\Project\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Services\EvaluationScoreKitService;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectLabel;
use Modules\Project\App\Models\Sprint;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskWorklog;
use Modules\Project\App\Services\ProjectService;
use Modules\Project\App\Services\SprintService;
use Modules\Project\App\Services\TaskScoreService;
use Modules\Project\App\Services\TaskService;
use Modules\Report\App\Models\Report;
use Modules\Report\App\Services\ReportService;

/**
 * Xoá báo cáo cũ (kể cả data prod đã import) rồi seed 1 dự án mẫu đầy đủ
 * trường + sprint/task/việc con/chấm điểm + báo cáo tháng/quý/khoảng ngày.
 *
 * Chạy riêng, không gắn DatabaseSeeder:
 *   php artisan db:seed --class="Modules\\Project\\Database\\Seeders\\SampleWorkspaceDemoSeeder"
 */
class SampleWorkspaceDemoSeeder extends Seeder
{
    public const PROJECT_NAME = 'VA Workspace — dự án mẫu đầy đủ';

    /** @var list<string> */
    private const MEMBER_EMAILS = [
        'khoana@hcm.vaschools.edu.vn',
        'ngocntk@hcm.vaschools.edu.vn',
        'quangtm@hcm.vaschools.edu.vn',
        'binhtl@hcm.vaschools.edu.vn',
        'kieunlt@hcm.vaschools.edu.vn',
        'thaipq@hcm.vaschools.edu.vn',
        'loccd@hcm.vaschools.edu.vn',
        'vunh@vaschools.edu.vn',
        'hungnv@vaschools.edu.vn',
        'truongnx@vaschools.edu.vn',
    ];

    public function run(): void
    {
        $department = Department::query()->where('code', 'CNTT')->first();
        $actor = User::query()->where('email', 'khoana@hcm.vaschools.edu.vn')->first()
            ?? User::query()->where('email', 'toanbq@vaschools.edu.vn')->first()
            ?? User::query()->orderBy('id')->first();

        if ($department === null || $actor === null) {
            $this->command?->warn('Thiếu phòng CNTT hoặc user — bỏ qua SampleWorkspaceDemoSeeder.');

            return;
        }

        $actor->department_id = $department->id;

        $this->clearReports();
        $this->ensureScoreKit($department, $actor);
        $project = $this->seedProject($department, $actor);
        $this->seedReports($department, $actor, $project);

        $this->command?->info('Đã xoá báo cáo cũ, seed dự án mẫu "'.self::PROJECT_NAME.'" và báo cáo tháng/quý/khoảng ngày.');
    }

    private function clearReports(): void
    {
        $count = Report::query()->count();
        Report::query()->delete();
        $this->command?->info("Đã xoá {$count} báo cáo.");
    }

    private function ensureScoreKit(Department $department, User $actor): void
    {
        $kit = EvaluationScoreKit::query()->where('department_id', $department->id)->first();
        if ($kit !== null && $kit->mode === EvaluationScoreKit::MODE_WEIGHTED_TASK) {
            return;
        }

        $difficulty = EvaluationCriteria::query()
            ->where('department_id', $department->id)
            ->where('name', 'Mức độ quan trọng của công việc')
            ->first();

        if ($difficulty !== null && ! $difficulty->use_for_task_type) {
            $difficulty->use_for_task_type = true;
            $difficulty->save();
        }

        app(EvaluationScoreKitService::class)->upsert((int) $department->id, (int) $actor->id, [
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'task_base_score' => 1,
            'difficulty_criterion_id' => $difficulty?->id,
            'difficulty_use_default' => $difficulty === null,
            'progress_use_default' => true,
            'quality_use_default' => true,
            'formula' => [
                'lock_difficulty' => 'on',
                'weight' => 'on',
                'progress' => 'on',
                'quality' => 'on',
            ],
        ]);
    }

    private function seedProject(Department $department, User $actor): Project
    {
        $users = User::query()->get()->keyBy(fn (User $user) => strtolower((string) $user->email));
        $lead = $users->get('khoana@hcm.vaschools.edu.vn') ?? $actor;
        $director = $users->get('toanbq@vaschools.edu.vn') ?? $actor;
        $deputy = $users->get('hoangbh@vaschools.edu.vn') ?? $director;

        $memberIds = collect(self::MEMBER_EMAILS)
            ->map(fn (string $email) => $users->get($email)?->id)
            ->filter()
            ->values()
            ->all();
        $followerIds = collect([$director->id, $deputy->id])->unique()->values()->all();

        $labels = $this->seedLabels((int) $actor->id);
        $projects = app(ProjectService::class);
        $existing = Project::query()->where('name', self::PROJECT_NAME)->first();

        if ($existing !== null) {
            $this->wipeProjectWork($existing);
            $project = $projects->update($existing, [
                'type' => 'Nội bộ',
                'name' => self::PROJECT_NAME,
                'lead_user_id' => $lead->id,
                'lead_department_id' => $department->id,
                'executing_department_ids' => [$department->id],
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'actual_start_date' => '2026-07-01',
                'progress_method' => 'task_weighted',
                'status' => 'in_progress',
                'importance' => 'strategic',
                'description' => 'Dự án mẫu đầy đủ trường: thành viên, nhãn, phạm vi, giai đoạn, đợt làm việc, danh mục, công việc cha/con, tỷ trọng, độ khó, giờ làm và phiếu chấm hiệu suất việc — dùng để kiểm tra /manager/project và khung điểm Cách 2.',
                'shift_task_dates_with_project' => true,
                'hide_cross_tasks_from_assignees' => false,
                'hide_child_tasks_from_followers' => false,
                'constrain_task_dates_to_project' => true,
                'member_ids' => $memberIds,
                'follower_ids' => $followerIds,
                'label_ids' => array_values($labels),
                'scopes' => [[
                    'scope_type' => 'department',
                    'department_id' => $department->id,
                    'weight_percent' => 100,
                ]],
            ], $actor);
            if (is_array($project)) {
                throw new \RuntimeException($project['error'] ?? 'Không cập nhật được dự án mẫu.');
            }
        } else {
            $project = $projects->create([
                'type' => 'Nội bộ',
                'name' => self::PROJECT_NAME,
                'lead_user_id' => $lead->id,
                'lead_department_id' => $department->id,
                'owner_department_id' => $department->id,
                'executing_department_ids' => [$department->id],
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'actual_start_date' => '2026-07-01',
                'progress_method' => 'task_weighted',
                'status' => 'in_progress',
                'importance' => 'strategic',
                'description' => 'Dự án mẫu đầy đủ trường: thành viên, nhãn, phạm vi, giai đoạn, đợt làm việc, danh mục, công việc cha/con, tỷ trọng, độ khó, giờ làm và phiếu chấm hiệu suất việc — dùng để kiểm tra /manager/project và khung điểm Cách 2.',
                'shift_task_dates_with_project' => true,
                'hide_cross_tasks_from_assignees' => false,
                'hide_child_tasks_from_followers' => false,
                'constrain_task_dates_to_project' => true,
                'member_ids' => $memberIds,
                'follower_ids' => $followerIds,
                'label_ids' => array_values($labels),
                'scopes' => [[
                    'scope_type' => 'department',
                    'department_id' => $department->id,
                    'weight_percent' => 100,
                ]],
            ], $actor);
        }

        $this->seedWork($project, $actor, $users, $lead, $director);

        return $project;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $users
     */
    private function seedWork(Project $project, User $actor, $users, User $lead, User $director): void
    {
        $tasks = app(TaskService::class);
        $sprints = app(SprintService::class);
        $scores = app(TaskScoreService::class);

        $binh = $users->get('binhtl@hcm.vaschools.edu.vn') ?? $lead;
        $ngoc = $users->get('ngocntk@hcm.vaschools.edu.vn') ?? $lead;
        $quang = $users->get('quangtm@hcm.vaschools.edu.vn') ?? $lead;
        $kieu = $users->get('kieunlt@hcm.vaschools.edu.vn') ?? $lead;
        $thai = $users->get('thaipq@hcm.vaschools.edu.vn') ?? $lead;

        $frontend = $this->task($tasks, $project, $actor, [
            'type' => 'category',
            'title' => 'Giao diện & trải nghiệm',
            'progress_type' => 'average',
            'sort_order' => 0,
        ]);
        $backend = $this->task($tasks, $project, $actor, [
            'type' => 'category',
            'title' => 'Nghiệp vụ & nền tảng',
            'progress_type' => 'task_weighted',
            'sort_order' => 1,
        ]);
        $qa = $this->task($tasks, $project, $actor, [
            'type' => 'category',
            'title' => 'Đảm bảo chất lượng',
            'progress_type' => 'average',
            'sort_order' => 2,
        ]);

        $phase1 = $this->task($tasks, $project, $actor, [
            'type' => 'phase',
            'title' => 'Giai đoạn 1 — Xây dựng nền tảng',
            'status' => 'in_progress',
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-30',
            'actual_start_date' => '2026-07-01',
            'progress_percent' => 72,
            'progress_type' => 'task_weighted',
            'description' => 'Thiết kế, phát triển module báo cáo / công việc và khung chấm điểm hiệu suất việc.',
            'sort_order' => 0,
        ]);
        $phase2 = $this->task($tasks, $project, $actor, [
            'type' => 'phase',
            'title' => 'Giai đoạn 2 — Mở rộng & vận hành',
            'status' => 'not_started',
            'start_date' => '2026-10-01',
            'end_date' => '2026-12-31',
            'progress_percent' => 0,
            'progress_type' => 'duration_weighted',
            'description' => 'Tối ưu hiệu năng, bàn giao vận hành, đào tạo người dùng.',
            'sort_order' => 1,
        ]);

        $sprint1 = $this->sprint($sprints, $project, $actor, $phase1, [
            'name' => 'Đợt 1 — Thiết kế & chốt luồng',
            'status' => 'completed',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'description' => 'Chốt thông tin, wireframe và luồng tạo báo cáo.',
            'sort_order' => 0,
        ]);
        $sprint2 = $this->sprint($sprints, $project, $actor, $phase1, [
            'name' => 'Đợt 2 — Module báo cáo',
            'status' => 'completed',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'description' => 'Danh sách báo cáo, wizard tạo, chấm điểm theo kỳ.',
            'sort_order' => 1,
        ]);
        $sprint3 = $this->sprint($sprints, $project, $actor, $phase1, [
            'name' => 'Đợt 3 — Công việc & việc con',
            'status' => 'active',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'description' => 'Sprint board, việc nhỏ, phân loại độ khó, tỷ trọng.',
            'sort_order' => 2,
        ]);
        $this->sprint($sprints, $project, $actor, $phase2, [
            'name' => 'Đợt 4 — Bàn giao vận hành',
            'status' => 'planned',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'description' => 'Tài liệu vận hành, đào tạo, hỗ trợ go-live.',
            'sort_order' => 0,
        ]);

        $design = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Thiết kế thông tin & luồng người dùng',
            'parent_id' => $frontend->id,
            'sprint_id' => $sprint1->id,
            'status' => 'completed',
            'priority' => 'high_priority',
            'weight' => 20,
            'assignee_id' => $kieu->id,
            'manager_id' => $lead->id,
            'watcher_ids' => [$director->id],
            'collaborator_ids' => [$ngoc->id],
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-20',
            'actual_start_date' => '2026-07-01',
            'actual_end_date' => '2026-07-18',
            'start_time' => '09:00',
            'due_time' => '18:00',
            'estimated_hours' => 32,
            'progress_percent' => 100,
            'progress_type' => 'child_weight',
            'description' => 'Chốt sitemap, luồng tạo báo cáo và màn hình chi tiết công việc cha/con.',
            'constrain_child_dates' => true,
            'sort_order' => 0,
        ]);
        $wire = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Wireframe màn hình báo cáo & dự án',
            'parent_id' => $design->id,
            'sprint_id' => $sprint1->id,
            'status' => 'completed',
            'priority' => 'important',
            'weight' => 50,
            'assignee_id' => $kieu->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-12',
            'actual_start_date' => '2026-07-01',
            'actual_end_date' => '2026-07-11',
            'estimated_hours' => 16,
            'progress_percent' => 100,
            'description' => 'Khung danh sách, panel chi tiết 28rem, modal tạo báo cáo.',
            'sort_order' => 0,
        ]);
        $tokens = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Hệ thống token giao diện sáng',
            'parent_id' => $design->id,
            'sprint_id' => $sprint1->id,
            'status' => 'completed',
            'priority' => 'assist',
            'weight' => 50,
            'assignee_id' => $ngoc->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-07-08',
            'end_date' => '2026-07-20',
            'actual_start_date' => '2026-07-08',
            'actual_end_date' => '2026-07-18',
            'estimated_hours' => 12,
            'progress_percent' => 100,
            'description' => 'Chỉ dùng bộ token light trong theme.css.',
            'sort_order' => 1,
        ]);

        $reportParent = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Phát triển module báo cáo đánh giá nhân sự',
            'parent_id' => $backend->id,
            'sprint_id' => $sprint2->id,
            'status' => 'completed',
            'priority' => 'strategic',
            'weight' => 35,
            'assignee_id' => $binh->id,
            'manager_id' => $lead->id,
            'watcher_ids' => [$director->id, $ngoc->id],
            'collaborator_ids' => [$quang->id],
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-25',
            'actual_start_date' => '2026-08-01',
            'actual_end_date' => '2026-08-24',
            'estimated_hours' => 64,
            'progress_percent' => 100,
            'progress_type' => 'child_weight',
            'description' => 'Danh sách, wizard kỳ tháng/quý/khoảng ngày, chốt phiên bản khung điểm.',
            'constrain_child_dates' => true,
            'sort_order' => 0,
        ]);
        $list = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Danh sách báo cáo — lọc kỳ và nhóm',
            'parent_id' => $reportParent->id,
            'sprint_id' => $sprint2->id,
            'status' => 'completed',
            'priority' => 'high_priority',
            'weight' => 40,
            'assignee_id' => $binh->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-12',
            'actual_start_date' => '2026-08-01',
            'actual_end_date' => '2026-08-12',
            'estimated_hours' => 24,
            'progress_percent' => 100,
            'sort_order' => 0,
        ]);
        $wizard = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Wizard tạo báo cáo theo tháng / quý / ngày',
            'parent_id' => $reportParent->id,
            'sprint_id' => $sprint2->id,
            'status' => 'completed',
            'priority' => 'important',
            'weight' => 60,
            'assignee_id' => $quang->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-25',
            'actual_start_date' => '2026-08-10',
            'actual_end_date' => '2026-08-24',
            'estimated_hours' => 28,
            'progress_percent' => 100,
            'sort_order' => 1,
        ]);

        $uxParent = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Phát triển giao diện học sinh làm bài trắc nghiệm & tự luận, tự động lưu',
            'parent_id' => $frontend->id,
            'sprint_id' => $sprint3->id,
            'status' => 'in_progress',
            'priority' => 'high_priority',
            'weight' => 25,
            'assignee_id' => $binh->id,
            'manager_id' => $lead->id,
            'watcher_ids' => [$ngoc->id],
            'collaborator_ids' => [$kieu->id],
            'start_date' => '2026-09-04',
            'end_date' => '2026-09-18',
            'actual_start_date' => '2026-09-04',
            'estimated_hours' => 40,
            'progress_percent' => 55,
            'progress_type' => 'child_weight',
            'description' => 'Việc cha — việc nhỏ phải hiện trạng thái, tỷ trọng và phân loại độ khó khi xem chi tiết.',
            'constrain_child_dates' => true,
            'hide_child_tasks_from_followers' => false,
            'allow_child_people_view_parent' => true,
            'sort_order' => 0,
        ]);
        $childSmall = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Công việc nhỏ — autosave bài làm',
            'parent_id' => $uxParent->id,
            'sprint_id' => $sprint3->id,
            'status' => 'completed',
            'priority' => 'important',
            'weight' => 40,
            'assignee_id' => $ngoc->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-09-04',
            'end_date' => '2026-09-10',
            'actual_start_date' => '2026-09-04',
            'actual_end_date' => '2026-09-09',
            'estimated_hours' => 12,
            'progress_percent' => 100,
            'description' => 'Việc nhỏ hoàn thành, có tỷ trọng 40% và phân loại Quan trọng.',
            'sort_order' => 0,
        ]);
        $childUi = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Việc nhỏ — cập nhật trạng thái trên bảng không cần tải lại',
            'parent_id' => $uxParent->id,
            'sprint_id' => $sprint3->id,
            'status' => 'in_progress',
            'priority' => 'high_priority',
            'weight' => 60,
            'assignee_id' => $binh->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-18',
            'actual_start_date' => '2026-09-08',
            'estimated_hours' => 16,
            'progress_percent' => 40,
            'description' => 'Sửa UI sprint board: đổi trạng thái phải nhảy ngay trên bảng.',
            'sort_order' => 1,
        ]);

        $overdue = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Tích hợp công cụ nhập công thức / ký hiệu toán học',
            'parent_id' => $frontend->id,
            'sprint_id' => $sprint3->id,
            'status' => 'in_progress',
            'priority' => 'assist',
            'weight' => 10,
            'assignee_id' => $quang->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-09-06',
            'end_date' => '2026-09-08',
            'actual_start_date' => '2026-09-06',
            'estimated_hours' => 10,
            'progress_percent' => 30,
            'description' => 'Cố ý quá hạn để kiểm tra cờ quá hạn trên bảng.',
            'sort_order' => 1,
        ]);
        $review = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => '[REVIEW] Demo module bài tập & trải nghiệm học sinh',
            'parent_id' => $qa->id,
            'sprint_id' => $sprint3->id,
            'status' => 'under_review',
            'priority' => 'important',
            'weight' => 10,
            'assignee_id' => $thai->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-09-12',
            'end_date' => '2026-09-19',
            'estimated_hours' => 8,
            'progress_percent' => 90,
            'description' => 'Phiên review cuối đợt 3.',
            'sort_order' => 0,
        ]);
        $hold = $this->task($tasks, $project, $actor, [
            'type' => 'task',
            'title' => 'Kiểm thử luồng giao bài — làm bài — nộp bài end-to-end',
            'parent_id' => $qa->id,
            'sprint_id' => $sprint3->id,
            'status' => 'on_hold',
            'priority' => 'support',
            'weight' => 5,
            'assignee_id' => $thai->id,
            'manager_id' => $lead->id,
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-22',
            'estimated_hours' => 6,
            'progress_percent' => 0,
            'sort_order' => 1,
        ]);

        $scored = [
            [$design, 5, 'Xuất sắc', true, 'Đúng hạn, wireframe dùng được ngay.'],
            [$wire, 4, 'Đạt', true, 'Đủ luồng chính, vài chỗ chỉnh spacing.'],
            [$tokens, 5, 'Xuất sắc', true, 'Token light thống nhất.'],
            [$reportParent, 5, 'Xuất sắc', true, 'Module báo cáo đủ kỳ tháng/quý/ngày.'],
            [$list, 4, 'Đạt', true, 'Lọc và nhóm dùng được.'],
            [$wizard, 4, 'Đạt', true, 'Wizard đủ 3 kiểu kỳ.'],
            [$childSmall, 4, 'Đạt', true, 'Autosave ổn định.'],
        ];
        foreach ($scored as [$task, $score, $result, $passed, $desc]) {
            $scores->upsert($task, [
                'rating_score' => $score,
                'rating_result' => $result,
                'is_passed' => $passed,
                'rating_desc' => $desc,
            ], $lead);
        }

        $this->worklog($wire, $kieu, '2026-07-03', 6, 'Phác khung danh sách báo cáo.');
        $this->worklog($wire, $kieu, '2026-07-08', 5, 'Panel chi tiết và thao tác dòng.');
        $this->worklog($tokens, $ngoc, '2026-07-10', 4, 'Rà token light.');
        $this->worklog($list, $binh, '2026-08-05', 8, 'Bảng báo cáo + nhóm theo ngày.');
        $this->worklog($wizard, $quang, '2026-08-18', 7, 'Kỳ tháng / quý / custom.');
        $this->worklog($childSmall, $ngoc, '2026-09-06', 5, 'Autosave bài làm.');
        $this->worklog($childUi, $binh, '2026-09-12', 4, 'Sửa nhảy trạng thái trên sprint board.');
        $this->worklog($overdue, $quang, '2026-09-07', 3, 'Khảo sát editor toán.');
        $this->worklog($review, $thai, '2026-09-15', 2, 'Chuẩn bị kịch bản review.');

        unset($phase2, $hold);
    }

    private function seedReports(Department $department, User $actor, Project $project): void
    {
        $reports = app(ReportService::class);
        $memberIds = $project->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $viewerIds = array_values(array_unique(array_merge($memberIds, [(int) $actor->id])));

        $specs = [
            [
                'title' => 'Đánh giá nhân sự tháng 07/2026',
                'period_type' => 'month',
                'period_from' => '2026-07-01',
                'period_to' => '2026-07-31',
                'save' => true,
            ],
            [
                'title' => 'Đánh giá nhân sự tháng 08/2026',
                'period_type' => 'month',
                'period_from' => '2026-08-01',
                'period_to' => '2026-08-31',
                'save' => true,
            ],
            [
                'title' => 'Đánh giá nhân sự tháng 09/2026',
                'period_type' => 'month',
                'period_from' => '2026-09-01',
                'period_to' => '2026-09-30',
                'save' => false,
            ],
            [
                'title' => 'Đánh giá nhân sự quý 3/2026',
                'period_type' => 'quarter',
                'period_from' => '2026-07-01',
                'period_to' => '2026-09-30',
                'save' => true,
            ],
            [
                'title' => 'Đánh giá nhân sự 01/09–18/09/2026',
                'period_type' => 'custom',
                'period_from' => '2026-09-01',
                'period_to' => '2026-09-18',
                'save' => false,
            ],
        ];

        foreach ($specs as $spec) {
            $report = $reports->createPersonnelEvaluation((int) $department->id, $actor, [
                'title' => $spec['title'],
                'period_type' => $spec['period_type'],
                'period_from' => $spec['period_from'],
                'period_to' => $spec['period_to'],
                'filter_user_ids' => $memberIds,
                'viewer_user_ids' => $viewerIds,
            ]);
            if ($spec['save']) {
                $reports->save($report, $actor);
            }
        }
    }

    private function wipeProjectWork(Project $project): void
    {
        $taskIds = Task::query()->where('project_id', $project->id)->pluck('id')->all();
        if ($taskIds !== []) {
            DB::table('comments')
                ->where('commentable_type', 'task')
                ->whereIn('commentable_id', $taskIds)
                ->delete();
        }

        Task::query()->where('project_id', $project->id)->update([
            'sprint_id' => null,
            'parent_id' => null,
        ]);
        Sprint::query()->where('project_id', $project->id)->delete();
        Task::query()->where('project_id', $project->id)->delete();
    }

    /** @return array<string, int> */
    private function seedLabels(int $createdBy): array
    {
        $defs = [
            'Ưu tiên' => 'danger',
            'Nội bộ' => 'primary',
            'Hiệu suất việc' => 'gold',
        ];
        $ids = [];
        foreach ($defs as $name => $color) {
            $label = ProjectLabel::query()->firstOrCreate(
                ['name' => $name],
                ['color' => $color, 'created_by' => $createdBy],
            );
            $ids[$name] = $label->id;
        }

        return $ids;
    }

    /** @param  array<string, mixed>  $data */
    private function task(TaskService $tasks, Project $project, User $actor, array $data): Task
    {
        $result = $tasks->create($project, $data, $actor);
        if (is_array($result)) {
            throw new \RuntimeException($result['error'] ?? 'Không tạo được công việc "'.($data['title'] ?? '').'".');
        }

        return $result;
    }

    /** @param  array<string, mixed>  $data */
    private function sprint(SprintService $sprints, Project $project, User $actor, Task $phase, array $data): Sprint
    {
        $result = $sprints->create($project, array_merge($data, ['phase_id' => $phase->id]), $actor);
        if (is_array($result)) {
            throw new \RuntimeException($result['error'] ?? 'Không tạo được đợt làm việc.');
        }

        return $result;
    }

    private function worklog(Task $task, User $user, string $date, float $hours, string $note): void
    {
        TaskWorklog::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'work_date' => $date,
            'hours' => $hours,
            'note' => $note,
            'created_by' => $user->id,
        ]);
    }
}
