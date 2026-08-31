<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Services\EvaluationScoreComputeService;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

/**
 * Chấm điểm chỉ được tính công việc THUỘC phòng ban đang báo cáo.
 *
 * Trước khi có phạm vi phòng ban, truy vấn chỉ lọc theo người thực hiện: nhân
 * viên phòng Nhân sự làm một việc của dự án phòng Công nghệ thì việc đó bị
 * cộng vào điểm phòng Nhân sự. Bộ test này giữ lại đúng tình huống đó.
 */
class EvaluationScoreDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-08-01';

    private const TO = '2026-08-31';

    private function department(string $code, string $name): Department
    {
        return Department::query()->create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function member(Department $dept): User
    {
        return User::factory()->create([
            'status' => 'active',
            'department_id' => $dept->id,
        ]);
    }

    private function version(Department $dept): EvaluationConfigVersion
    {
        return EvaluationConfigVersion::query()->create([
            'department_id' => $dept->id,
            'version_no' => 1,
            'status' => EvaluationConfigVersion::STATUS_ACTIVE,
            'kit_snapshot' => [
                'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
                'base_score' => 100,
                'points_per_completed_task' => 5,
                'points_per_incomplete_task' => 0,
                'formula' => array_merge(EvaluationScoreKit::defaultFormula(), ['done' => 'add']),
                'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
            ],
            'criteria_snapshot' => [],
            'published_at' => now(),
            'effective_from' => self::FROM,
        ]);
    }

    private function project(string $code, ?Department $owner, ?Department $executing = null): Project
    {
        return Project::query()->create([
            'code' => $code,
            'type' => 'project',
            'name' => 'Dự án '.$code,
            'owner_department_id' => $owner?->id,
            'executing_department_id' => $executing?->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function task(User $assignee, array $attributes = []): Task
    {
        $guarded = array_intersect_key($attributes, array_flip([
            'origin_department_id',
            'delegated_to_department_id',
            'delegation_status',
        ]));

        $task = Task::query()->create(array_merge([
            'type' => 'task',
            'title' => 'Việc mẫu',
            'status' => 'completed',
            'assignee_id' => $assignee->id,
            'end_date' => '2026-08-20',
            'actual_end_date' => '2026-08-20',
            'progress_type' => 'percent',
            'sort_order' => 0,
        ], array_diff_key($attributes, $guarded)));

        if ($guarded !== []) {
            $task->forceFill($guarded)->save();
        }

        return $task;
    }

    private function service(): EvaluationScoreComputeService
    {
        return app(EvaluationScoreComputeService::class);
    }

    /**
     * Test thăm dò đã phát hiện lỗi rò rỉ — giữ lại làm test hồi quy.
     */
    public function test_task_of_another_department_project_does_not_count(): void
    {
        $hr = $this->department('HR', 'Nhân sự');
        $it = $this->department('IT', 'Công nghệ');
        $user = $this->member($hr);

        // Việc thuộc dự án của chính phòng Nhân sự — phải tính.
        $this->task($user, ['project_id' => $this->project('HR1', $hr)->id]);

        // Việc thuộc dự án của phòng Công nghệ — KHÔNG được tính vào phòng
        // Nhân sự, dù người thực hiện là nhân viên phòng Nhân sự.
        $this->task($user, ['project_id' => $this->project('IT1', $it)->id]);

        $row = $this->service()->computeForUser(
            $user->id,
            $user->name,
            $this->version($hr),
            self::FROM,
            self::TO,
        );

        $this->assertSame(1, $row['task_count']);
        $this->assertSame(105.0, $row['final_score']);
    }

    public function test_project_executing_department_also_counts(): void
    {
        $hr = $this->department('HR', 'Nhân sự');
        $it = $this->department('IT', 'Công nghệ');
        $user = $this->member($hr);

        // Dự án phòng Công nghệ sở hữu nhưng phòng Nhân sự thực hiện chính.
        $this->task($user, ['project_id' => $this->project('IT2', $it, $hr)->id]);

        $row = $this->service()->computeForUser(
            $user->id,
            $user->name,
            $this->version($hr),
            self::FROM,
            self::TO,
        );

        $this->assertSame(1, $row['task_count']);
    }

    public function test_standalone_task_follows_origin_department(): void
    {
        $hr = $this->department('HR', 'Nhân sự');
        $it = $this->department('IT', 'Công nghệ');
        $user = $this->member($hr);

        // Việc đứng riêng phát sinh ở phòng Công nghệ — không tính cho Nhân sự.
        $this->task($user, ['origin_department_id' => $it->id]);
        // Việc đứng riêng phát sinh ở chính phòng Nhân sự — có tính.
        $this->task($user, ['origin_department_id' => $hr->id]);

        $row = $this->service()->computeForUser(
            $user->id,
            $user->name,
            $this->version($hr),
            self::FROM,
            self::TO,
        );

        $this->assertSame(1, $row['task_count']);
    }

    public function test_standalone_task_without_origin_follows_assignee_department(): void
    {
        $hr = $this->department('HR', 'Nhân sự');
        $user = $this->member($hr);

        $this->task($user);

        $row = $this->service()->computeForUser(
            $user->id,
            $user->name,
            $this->version($hr),
            self::FROM,
            self::TO,
        );

        $this->assertSame(1, $row['task_count']);
    }

    /**
     * Việc chuyển giao tính cho phòng NHẬN — người thực hiện là nhân sự phòng
     * nhận nên điểm khớp với người trong báo cáo.
     */
    public function test_delegated_task_counts_for_receiving_department(): void
    {
        $hr = $this->department('HR', 'Nhân sự');
        $it = $this->department('IT', 'Công nghệ');
        $receiver = $this->member($it);

        $this->task($receiver, [
            'origin_department_id' => $hr->id,
            'delegated_to_department_id' => $it->id,
            'delegation_status' => 'accepted',
        ]);

        $itRow = $this->service()->computeForUser(
            $receiver->id,
            $receiver->name,
            $this->version($it),
            self::FROM,
            self::TO,
        );
        $this->assertSame(1, $itRow['task_count']);

        // Phòng giao không được đếm lại việc đó nữa.
        $hrRow = $this->service()->computeForUser(
            $receiver->id,
            $receiver->name,
            $this->version($hr),
            self::FROM,
            self::TO,
        );
        $this->assertSame(0, $hrRow['task_count']);
    }

    public function test_rejected_delegation_falls_back_to_origin_department(): void
    {
        $hr = $this->department('HR', 'Nhân sự');
        $it = $this->department('IT', 'Công nghệ');
        $user = $this->member($hr);

        $this->task($user, [
            'origin_department_id' => $hr->id,
            'delegated_to_department_id' => $it->id,
            'delegation_status' => 'rejected',
        ]);

        $hrRow = $this->service()->computeForUser(
            $user->id,
            $user->name,
            $this->version($hr),
            self::FROM,
            self::TO,
        );
        $this->assertSame(1, $hrRow['task_count']);

        $itRow = $this->service()->computeForUser(
            $user->id,
            $user->name,
            $this->version($it),
            self::FROM,
            self::TO,
        );
        $this->assertSame(0, $itRow['task_count']);
    }
}
