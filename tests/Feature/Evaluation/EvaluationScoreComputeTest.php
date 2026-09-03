<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Services\EvaluationScoreComputeService;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskScore;
use Tests\TestCase;

class EvaluationScoreComputeTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-08-01';

    private const TO = '2026-08-31';

    private function department(): Department
    {
        return Department::query()->create([
            'code' => 'HR',
            'name' => 'Nhân sự',
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

    /** @param array<string, mixed> $kit */
    private function version(Department $dept, array $kit, array $criteria = []): EvaluationConfigVersion
    {
        return EvaluationConfigVersion::query()->create([
            'department_id' => $dept->id,
            'version_no' => 1,
            'status' => EvaluationConfigVersion::STATUS_ACTIVE,
            'kit_snapshot' => $kit,
            'criteria_snapshot' => $criteria,
            'published_at' => now(),
            'effective_from' => self::FROM,
        ]);
    }

    private function task(User $assignee, array $attributes = []): Task
    {
        return Task::query()->create(array_merge([
            'type' => 'task',
            'title' => 'Việc mẫu',
            'status' => 'completed',
            'assignee_id' => $assignee->id,
            'end_date' => '2026-08-20',
            'actual_end_date' => '2026-08-20',
            'progress_type' => 'percent',
            'sort_order' => 0,
        ], $attributes));
    }

    private function service(): EvaluationScoreComputeService
    {
        return app(EvaluationScoreComputeService::class);
    }

    public function test_base_adjust_adds_points_per_completed_task(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 5,
            'points_per_incomplete_task' => 2,
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'done' => 'add',
                'undone' => 'sub',
            ]),
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
        ]);

        $this->task($user);
        $this->task($user);
        $this->task($user, ['status' => 'in_progress', 'actual_end_date' => null]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        // 2 việc xong (+5 mỗi việc), 1 việc dở (-2)
        $this->assertSame(100.0, $row['start_score']);
        $this->assertSame(8.0, $row['task_adjustment']);
        $this->assertSame(108.0, $row['final_score']);
        $this->assertCount(3, $row['task_breakdown']);
    }

    public function test_events_add_bonus_and_penalty(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động lập kế hoạch', 'score' => 6]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 0,
            'points_per_incomplete_task' => 0,
            'formula' => EvaluationScoreKit::defaultFormula(),
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
        ]);

        EvaluationEvent::query()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'criterion_snapshot' => ['name' => 'Chủ động'],
            'level_code' => 'A1',
            'level_label' => 'Chủ động lập kế hoạch',
            'score' => 6,
            'occurred_at' => '2026-08-10',
            'status' => EvaluationEvent::STATUS_APPROVED,
        ]);

        EvaluationEvent::query()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'criterion_snapshot' => ['name' => 'Kỷ luật'],
            'level_code' => 'B1',
            'level_label' => 'Trễ deadline',
            'score' => -2,
            'occurred_at' => '2026-08-12',
            'status' => EvaluationEvent::STATUS_APPROVED,
        ]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(6.0, $row['bonus']);
        $this->assertSame(2.0, $row['penalty']);
        $this->assertSame(104.0, $row['final_score']);
        $this->assertCount(2, $row['event_breakdown']);
    }

    public function test_pending_and_out_of_period_events_are_ignored(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'formula' => EvaluationScoreKit::defaultFormula(),
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
        ]);

        // Chờ duyệt — không được tính
        EvaluationEvent::query()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'level_code' => 'A1',
            'level_label' => 'Chủ động',
            'score' => 50,
            'occurred_at' => '2026-08-10',
            'status' => EvaluationEvent::STATUS_PENDING,
        ]);

        // Ngoài kỳ — không được tính
        EvaluationEvent::query()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'level_code' => 'A2',
            'level_label' => 'Chủ động',
            'score' => 30,
            'occurred_at' => '2026-07-15',
            'status' => EvaluationEvent::STATUS_APPROVED,
        ]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(0.0, $row['bonus']);
        $this->assertSame(100.0, $row['final_score']);
    }

    public function test_classification_picks_highest_reached_level(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 95,
            'formula' => EvaluationScoreKit::defaultFormula(),
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
        ]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        // Thang mặc định: 110 Xuất sắc, 100 Tốt, 90 Khá, 80 Đạt, 0 Chưa đạt
        $this->assertSame(95.0, $row['final_score']);
        $this->assertSame('K', $row['classification_code']);
        $this->assertSame('Khá', $row['classification_label']);
    }

    public function test_weighted_task_computes_performance_percentage(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'task_base_score' => 100,
            'quality_bonus_percent' => 0,
            'weighted_task_levels' => EvaluationScoreKit::defaultWeightedTaskLevels(),
            'progress_levels' => EvaluationScoreKit::defaultProgressLevels(),
            'quality_levels' => EvaluationScoreKit::defaultQualityLevels(),
            'performance_levels' => EvaluationScoreKit::defaultPerformanceLevels(),
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'on',
                'quality' => 'off',
            ]),
        ]);

        // Đúng hạn — hệ số tiến độ 1.0, nên hiệu suất đạt 100%
        $this->task($user, ['end_date' => '2026-08-20', 'actual_end_date' => '2026-08-20']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(0.0, $row['start_score']);
        $this->assertSame(100.0, $row['final_score']);
        $this->assertSame('XS', $row['classification_code']);
    }

    public function test_weighted_task_penalises_late_completion(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'task_base_score' => 100,
            'quality_bonus_percent' => 0,
            'weighted_task_levels' => EvaluationScoreKit::defaultWeightedTaskLevels(),
            'progress_levels' => EvaluationScoreKit::defaultProgressLevels(),
            'quality_levels' => EvaluationScoreKit::defaultQualityLevels(),
            'performance_levels' => EvaluationScoreKit::defaultPerformanceLevels(),
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'on',
                'quality' => 'off',
            ]),
        ]);

        // Trễ 3 ngày so với hạn
        $this->task($user, ['end_date' => '2026-08-20', 'actual_end_date' => '2026-08-23']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertLessThan(100.0, $row['final_score']);
        $this->assertGreaterThan(0.0, $row['final_score']);
    }

    public function test_quality_rating_lowers_actual_score(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'task_base_score' => 100,
            'quality_bonus_percent' => 0,
            'weighted_task_levels' => EvaluationScoreKit::defaultWeightedTaskLevels(),
            'progress_levels' => EvaluationScoreKit::defaultProgressLevels(),
            'quality_levels' => EvaluationScoreKit::defaultQualityLevels(),
            'performance_levels' => EvaluationScoreKit::defaultPerformanceLevels(),
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'off',
                'quality' => 'on',
            ]),
        ]);

        $task = $this->task($user);
        TaskScore::query()->create([
            'task_id' => $task->id,
            'rating_result' => 'Cần sửa', // hệ số 0.8 trong thang mặc định
        ]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(80.0, $row['final_score']);
    }

    public function test_tasks_outside_period_are_excluded(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 5,
            'formula' => EvaluationScoreKit::defaultFormula(),
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
        ]);

        $this->task($user, ['end_date' => '2026-07-10', 'actual_end_date' => '2026-07-10']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(0, $row['task_count']);
        $this->assertSame(100.0, $row['final_score']);
    }

    public function test_summary_counts_people_and_distribution(): void
    {
        $dept = $this->department();
        $first = $this->member($dept);
        $second = $this->member($dept);

        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 10,
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), ['done' => 'add']),
            'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
        ]);

        $this->task($first);

        $result = $this->service()->computeForPeople(
            [
                ['id' => $first->id, 'name' => $first->name],
                ['id' => $second->id, 'name' => $second->name],
            ],
            $version,
            self::FROM,
            self::TO,
        );

        $this->assertSame(2, $result['summary']['total_people']);
        $this->assertSame(105.0, $result['summary']['average_score']);
        $this->assertSame(110.0, $result['summary']['highest_score']);
        $this->assertSame(100.0, $result['summary']['lowest_score']);

        $excellent = collect($result['summary']['distribution'])->firstWhere('code', 'XS');
        $this->assertSame(1, $excellent['count']);
    }

    /** @return array<string, mixed> */
    private function weightedV2Kit(array $overrides = []): array
    {
        $progress = EvaluationScoreKit::defaultProgressLevels();

        return array_merge([
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'kit_schema_version' => EvaluationScoreKit::KIT_SCHEMA_VERSION,
            'task_base_score' => 100,
            'quality_bonus_percent' => 0,
            'weighted_task_levels' => EvaluationScoreKit::defaultWeightedTaskLevels(),
            'progress_levels' => $progress,
            'quality_levels' => EvaluationScoreKit::defaultQualityLevels(),
            'progress_bands' => EvaluationScoreKit::buildProgressBands($progress),
            'performance_levels' => EvaluationScoreKit::defaultPerformanceLevels(),
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'on',
                'quality' => 'off',
            ]),
        ], $overrides);
    }

    public function test_v2_incomplete_overdue_tasks_score_zero_not_five_hundred(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);
        $version = $this->version($dept, $this->weightedV2Kit());

        for ($i = 0; $i < 4; $i++) {
            $this->task($user, [
                'title' => 'Việc dở '.$i,
                'status' => 'in_progress',
                'end_date' => '2026-08-10',
                'actual_end_date' => null,
            ]);
        }

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertTrue($row['has_task_basis']);
        $this->assertSame(0.0, $row['task_adjustment']);
        $this->assertSame(0.0, $row['final_score']);
        $this->assertSame('KD', $row['classification_code']);
        $this->assertSame('Không đạt', $row['classification_label']);
        foreach ($row['task_breakdown'] as $task) {
            $this->assertSame(0.0, $task['actual_score']);
            $this->assertSame('incomplete', $task['zeroed_reason']);
        }
    }

    public function test_legacy_ordinal_progress_snapshot_still_explodes_for_incomplete(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);
        $version = $this->version($dept, [
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'task_base_score' => 100,
            'quality_bonus_percent' => 0,
            'progress_levels' => [
                ['code' => '1', 'label' => 'Tốt', 'score' => 1],
                ['code' => '5', 'label' => 'Kém', 'score' => 5],
            ],
            'performance_levels' => EvaluationScoreKit::defaultPerformanceLevels(),
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'on',
                'quality' => 'off',
            ]),
        ]);

        $this->task($user, [
            'status' => 'in_progress',
            'end_date' => '2026-08-10',
            'actual_end_date' => null,
        ]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertFalse(EvaluationScoreKit::isSchemaV2($version->kit_snapshot));
        $this->assertSame(500.0, $row['final_score']);
    }

    public function test_v2_missing_quality_on_completed_task_is_zero(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);
        $version = $this->version($dept, $this->weightedV2Kit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'off',
                'quality' => 'on',
            ]),
        ]));

        $this->task($user);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(1, $row['missing']['quality']);
        $this->assertSame(0.0, $row['final_score']);
        $this->assertSame('missing_data', $row['task_breakdown'][0]['zeroed_reason']);
        $this->assertSame(['quality'], $row['task_breakdown'][0]['missing_fields']);
    }

    public function test_v2_no_tasks_is_insufficient_basis(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);
        $version = $this->version($dept, $this->weightedV2Kit());

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertFalse($row['has_task_basis']);
        $this->assertSame(0.0, $row['final_score']);
        $this->assertNull($row['classification_code']);
        $this->assertSame('Chưa đủ cơ sở', $row['classification_label']);
    }

    public function test_v2_converted_ordinal_progress_stays_in_factor_band(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);
        $converted = EvaluationScoreKit::convertCriterionLevelsToFactors([
            ['code' => '1', 'label' => 'Xuất sắc', 'score' => 5],
            ['code' => '2', 'label' => 'Tốt', 'score' => 4],
            ['code' => '3', 'label' => 'Khá', 'score' => 3],
            ['code' => '4', 'label' => 'Đạt', 'score' => 2],
            ['code' => '5', 'label' => 'Kém', 'score' => 1],
        ], 'progress');

        $version = $this->version($dept, $this->weightedV2Kit([
            'progress_levels' => $converted,
            'progress_bands' => EvaluationScoreKit::buildProgressBands($converted),
        ]));

        $this->task($user, ['end_date' => '2026-08-20', 'actual_end_date' => '2026-08-20']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertGreaterThanOrEqual(50.0, $row['final_score']);
        $this->assertLessThanOrEqual(110.0, $row['final_score']);
        $this->assertNotSame(500.0, $row['final_score']);
    }

    public function test_v2_event_points_add_to_performance_percent(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);
        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động', 'score' => 6]],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $version = $this->version($dept, $this->weightedV2Kit());

        $this->task($user, ['end_date' => '2026-08-20', 'actual_end_date' => '2026-08-20']);

        EvaluationEvent::query()->create([
            'department_id' => $dept->id,
            'user_id' => $user->id,
            'criterion_id' => $criterion->id,
            'criterion_snapshot' => ['name' => 'Chủ động'],
            'level_code' => 'A1',
            'level_label' => 'Chủ động',
            'score' => 6,
            'occurred_at' => '2026-08-10',
            'status' => EvaluationEvent::STATUS_APPROVED,
        ]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(100.0, $row['task_performance_percent']);
        $this->assertSame(6.0, $row['event_adjustment_points']);
        $this->assertSame(106.0, $row['final_score']);
        $this->assertSame(106.0, $row['final_performance_percent']);
    }
}
