<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Services\ProjectProgressCalculator;
use Tests\TestCase;

/**
 * % tiến độ dự án roll-up từ Task lá.
 *
 * Trước đây ProjectService::present() trả cứng progress_percent = null nên
 * trang Dự án luôn hiện "—" trong khi Dashboard tổng công ty (dùng
 * ProjectProgressCalculator) hiện đúng số. Bộ test này chốt 2 điều:
 * 1. Công thức của cả 3 progress_method đúng, kể cả fallback.
 * 2. Trang Dự án và Dashboard ra CÙNG một con số cho cùng 1 dự án.
 */
class ProjectProgressRollupTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = ['department_director']): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $user->roles()->sync(Role::query()->whereIn('code', $roles)->pluck('id'));

        return $user;
    }

    private function makeProject(array $attributes = []): Project
    {
        return Project::query()->create(array_merge([
            'code' => 'PRJ'.random_int(1000, 999999),
            'type' => 'internal',
            'name' => 'Dự án thử nghiệm',
            'progress_method' => 'average',
            'status' => 'in_progress',
            'importance' => 'important',
        ], $attributes));
    }

    private function makeTask(Project $project, array $attributes = []): Task
    {
        return Task::query()->create(array_merge([
            'project_id' => $project->id,
            'type' => 'task',
            'title' => 'Công việc thử nghiệm',
            'status' => 'in_progress',
        ], $attributes));
    }

    private function calculator(): ProjectProgressCalculator
    {
        return app(ProjectProgressCalculator::class);
    }

    public function test_average_method_takes_mean_of_task_progress(): void
    {
        $project = $this->makeProject(['progress_method' => 'average']);
        $this->makeTask($project, ['progress_percent' => 40]);
        $this->makeTask($project, ['progress_percent' => 60]);

        // (40 + 60) / 2 = 50
        $this->assertSame(50.0, $this->calculator()->resolveOne($project->id, 'average'));
    }

    public function test_duration_weighted_method_weights_by_task_days(): void
    {
        $project = $this->makeProject(['progress_method' => 'duration_weighted']);
        // 4 ngày, tiến độ 40%
        $this->makeTask($project, [
            'progress_percent' => 40,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-04',
        ]);
        // 6 ngày, tiến độ 50%
        $this->makeTask($project, [
            'progress_percent' => 50,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-06',
        ]);

        // DATEDIFF+1 → 4 và 6 ngày: (4*40 + 6*50) / (10*100) * 100 = 46
        $this->assertSame(46.0, $this->calculator()->resolveOne($project->id, 'duration_weighted'));
    }

    public function test_task_weighted_method_weights_by_weight_column(): void
    {
        $project = $this->makeProject(['progress_method' => 'task_weighted']);
        $this->makeTask($project, ['progress_percent' => 50, 'weight' => 40]);
        $this->makeTask($project, ['progress_percent' => 40, 'weight' => 30]);

        // (40*50 + 30*40) / (40+30) = 45.71...
        $this->assertSame(45.7, $this->calculator()->resolveOne($project->id, 'task_weighted'));
    }

    public function test_task_weighted_falls_back_to_average_when_no_weight(): void
    {
        $project = $this->makeProject(['progress_method' => 'task_weighted']);
        $this->makeTask($project, ['progress_percent' => 40, 'weight' => null]);
        $this->makeTask($project, ['progress_percent' => 60, 'weight' => null]);

        // Không task nào có weight hợp lệ → về average = 50.
        $this->assertSame(50.0, $this->calculator()->resolveOne($project->id, 'task_weighted'));
    }

    public function test_project_without_tasks_has_null_progress_not_zero(): void
    {
        $project = $this->makeProject();

        // Chưa có dữ liệu KHÁC 0% — 0% nghĩa là có việc nhưng chưa ai làm.
        $this->assertNull($this->calculator()->resolveOne($project->id, 'average'));
    }

    public function test_cancelled_tasks_are_excluded_from_both_sides_of_the_ratio(): void
    {
        $project = $this->makeProject(['progress_method' => 'average']);
        $this->makeTask($project, ['progress_percent' => 40]);
        $this->makeTask($project, ['progress_percent' => 60]);
        // Việc đã huỷ không được kéo tiến độ xuống.
        $this->makeTask($project, ['progress_percent' => 0, 'status' => 'cancelled']);

        $this->assertSame(50.0, $this->calculator()->resolveOne($project->id, 'average'));
    }

    public function test_phase_and_category_nodes_are_excluded(): void
    {
        $project = $this->makeProject(['progress_method' => 'average']);
        $this->makeTask($project, ['progress_percent' => 40]);
        $this->makeTask($project, ['progress_percent' => 60]);
        // Chỉ task lá được tính — danh mục/giai đoạn không phải việc thật.
        $this->makeTask($project, ['progress_percent' => 100, 'type' => 'phase', 'title' => 'Giai đoạn 1']);
        $this->makeTask($project, ['progress_percent' => 100, 'type' => 'category', 'title' => 'Danh mục 1']);

        $this->assertSame(50.0, $this->calculator()->resolveOne($project->id, 'average'));
    }

    public function test_resolve_many_matches_resolve_one_for_every_project(): void
    {
        $a = $this->makeProject(['progress_method' => 'average']);
        $this->makeTask($a, ['progress_percent' => 40]);
        $this->makeTask($a, ['progress_percent' => 60]);

        $b = $this->makeProject(['progress_method' => 'task_weighted']);
        $this->makeTask($b, ['progress_percent' => 50, 'weight' => 40]);
        $this->makeTask($b, ['progress_percent' => 40, 'weight' => 30]);

        $empty = $this->makeProject();

        $calculator = $this->calculator();
        $many = $calculator->resolveMany(collect([$a, $b, $empty]));

        $this->assertSame($calculator->resolveOne($a->id, 'average'), $many[$a->id]);
        $this->assertSame($calculator->resolveOne($b->id, 'task_weighted'), $many[$b->id]);
        $this->assertNull($many[$empty->id]);
    }

    /**
     * Chốt lỗi gốc: trang Dự án và Dashboard tổng công ty phải ra cùng 1 số.
     */
    public function test_project_list_and_dashboard_report_the_same_progress(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $viewer = $this->makeUser(
            ['department_id' => $dept->id],
            ['super_admin'],
        );

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $viewer->id,
            'progress_method' => 'average',
        ]);
        $this->makeTask($project, ['progress_percent' => 40]);
        $this->makeTask($project, ['progress_percent' => 60]);

        $list = $this->actingAs($viewer)->getJson('/api/project');
        $list->assertOk();
        $row = collect($list->json('projects'))->firstWhere('id', $project->id);

        $this->assertNotNull($row, 'Dự án vừa tạo phải có trong danh sách.');
        $this->assertSame(50.0, (float) $row['progress_percent']);

        $dashboard = $this->actingAs($viewer)->getJson('/api/dashboard/company/projects');
        $dashboard->assertOk();
        $dashRow = collect($dashboard->json('data'))->firstWhere('id', $project->id);

        $this->assertNotNull($dashRow, 'Dự án vừa tạo phải có trong bảng dashboard.');
        $this->assertSame(
            (float) $row['progress_percent'],
            (float) $dashRow['progress_percent'],
            'Tiến độ ở trang Dự án và Dashboard phải bằng nhau.',
        );
    }

    public function test_project_detail_also_returns_rolled_up_progress(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $viewer = $this->makeUser(['department_id' => $dept->id], ['super_admin']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $viewer->id,
            'progress_method' => 'average',
        ]);
        $this->makeTask($project, ['progress_percent' => 30]);
        $this->makeTask($project, ['progress_percent' => 70]);

        $detail = $this->actingAs($viewer)->getJson("/api/project/{$project->id}");
        $detail->assertOk();
        $this->assertSame(50.0, (float) $detail->json('project.progress_percent'));
    }
}
