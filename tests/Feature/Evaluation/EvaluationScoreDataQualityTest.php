<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Services\EvaluationConfigVersionService;
use Modules\Evaluation\App\Services\EvaluationScoreComputeService;
use Modules\Identity\App\Models\Department;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Models\TaskScore;
use Tests\TestCase;

/**
 * Độ khó và chất lượng phải khớp đúng nguồn, và phần không tính được phải
 * hiện ra thay vì âm thầm rơi về hệ số 1.0.
 */
class EvaluationScoreDataQualityTest extends TestCase
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

    /** @param array<string, mixed> $attributes */
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

    /** @param array<string, mixed> $kit */
    private function version(Department $dept, array $kit): EvaluationConfigVersion
    {
        return EvaluationConfigVersion::query()->create([
            'department_id' => $dept->id,
            'version_no' => 1,
            'status' => EvaluationConfigVersion::STATUS_ACTIVE,
            'kit_snapshot' => $kit,
            'criteria_snapshot' => [],
            'published_at' => now(),
            'effective_from' => self::FROM,
        ]);
    }

    /** @return array<string, mixed> */
    private function weightedKit(array $overrides = []): array
    {
        return array_merge([
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
                'quality' => 'off',
            ]),
        ], $overrides);
    }

    /**
     * I-2 — độ khó tra theo bảng chụp, không phải theo mã của thang độ khó.
     *
     * Task lưu giá trị đã quy chuẩn của tiêu chí loại công việc
     * (`high_priority`), còn thang độ khó lưu mã riêng (`KH`). Không có bảng
     * tra thì hai bên không gặp nhau và mọi việc rơi về hệ số 1.0.
     */
    public function test_difficulty_uses_snapshot_lookup_not_scale_code(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'difficulty_lookup' => [
                'high_priority' => 1.20,
                'kh' => 1.20,
            ],
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'on',
                'progress' => 'off',
                'quality' => 'off',
            ]),
        ]));

        $this->task($user, ['priority' => 'high_priority']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        // Điểm chuẩn và điểm thực cùng nhân hệ số 1.2 nên hiệu suất vẫn 100%,
        // nhưng hệ số phải tra ra 1.2 chứ không phải mặc định 1.0.
        $this->assertSame(1.2, $row['task_breakdown'][0]['difficulty_factor']);
        $this->assertSame(0, $row['missing']['difficulty']);
    }

    public function test_unmatched_difficulty_is_counted_as_missing(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'difficulty_lookup' => ['kh' => 1.20],
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'on',
                'progress' => 'off',
                'quality' => 'off',
            ]),
        ]));

        $this->task($user, ['priority' => 'khong_co_trong_thang']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(1, $row['missing']['difficulty']);
        $this->assertSame(1.0, $row['task_breakdown'][0]['difficulty_factor']);
    }

    /**
     * I-2 — bản chụp chốt trước khi có bảng tra vẫn phải mở được, tra theo
     * thang độ khó như cũ.
     */
    public function test_old_snapshot_without_lookup_still_matches_scale_code(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'on',
                'progress' => 'off',
                'quality' => 'off',
            ]),
        ]));

        $this->task($user, ['priority' => 'KH']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(1.2, $row['task_breakdown'][0]['difficulty_factor']);
        $this->assertSame(0, $row['missing']['difficulty']);
    }

    /**
     * I-2 — publish() chụp bảng tra gộp cả mã thang, mã tiêu chí nguồn và giá
     * trị quy chuẩn mà Task thực sự lưu.
     */
    public function test_publish_snapshots_difficulty_lookup(): void
    {
        $dept = $this->department();

        // Tiêu chí loại công việc — nguồn sinh ra giá trị trên task.priority.
        EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Loại công việc',
            'type' => 'scale',
            'use_for_task_type' => true,
            'levels' => [
                ['code' => 'B1-4', 'label' => 'Ưu tiên cao', 'score' => 4],
                ['code' => 'B1-3', 'label' => 'Quan trọng', 'score' => 3],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $publisher = $this->member($dept);
        $version = app(EvaluationConfigVersionService::class)->publish($dept->id, $publisher->id);
        $lookup = $version->kit_snapshot['difficulty_lookup'] ?? [];

        $this->assertNotSame([], $lookup);
        // Giá trị quy chuẩn mà TaskImportanceOptions sinh ra phải tra được.
        $this->assertArrayHasKey('high_priority', $lookup);
        $this->assertArrayHasKey('important', $lookup);
        // Mã của chính tiêu chí nguồn cũng tra được.
        $this->assertArrayHasKey('b1-4', $lookup);
    }

    /**
     * I-3 — người chấm gõ thiếu dấu / khác hoa thường vẫn phải ra đúng mức.
     */
    public function test_quality_matches_loosely_when_accents_differ(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'off',
                'quality' => 'on',
            ]),
        ]));

        // Thang mặc định có mức "Cần sửa" hệ số 0.8.
        $task = $this->task($user);
        TaskScore::query()->create(['task_id' => $task->id, 'rating_result' => 'can  SUA']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(80.0, $row['final_score']);
        $this->assertSame(0, $row['missing']['quality']);
    }

    /** I-3 / I-5 — gõ hẳn một chữ khác thì phải bị đếm là chưa tính được. */
    public function test_unmatched_quality_is_counted_as_missing(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'off',
                'quality' => 'on',
            ]),
        ]));

        $task = $this->task($user);
        TaskScore::query()->create(['task_id' => $task->id, 'rating_result' => 'chưa ổn']);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(1, $row['missing']['quality']);
        $this->assertSame(1, $row['missing_total']);
        // Vẫn tính hệ số 1.0 như trước, nhưng giờ có báo ra.
        $this->assertSame(100.0, $row['final_score']);
    }

    public function test_task_without_rating_is_counted_as_missing_quality(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'off',
                'quality' => 'on',
            ]),
        ]));

        $this->task($user);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(1, $row['missing']['quality']);
    }

    /** I-5 — việc chưa có ngày hoàn thành thực tế bị đếm vào phần tiến độ. */
    public function test_completed_task_without_actual_date_is_missing_progress(): void
    {
        $dept = $this->department();
        $user = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'on',
                'quality' => 'off',
            ]),
        ]));

        $this->task($user, ['actual_end_date' => null]);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(1, $row['missing']['progress']);
    }

    /** I-5 — tổng hợp cả phòng ban cộng dồn phần thiếu của từng người. */
    public function test_summary_totals_missing_across_people(): void
    {
        $dept = $this->department();
        $first = $this->member($dept);
        $second = $this->member($dept);

        $version = $this->version($dept, $this->weightedKit([
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), [
                'weight' => 'off',
                'progress' => 'off',
                'quality' => 'on',
            ]),
        ]));

        $this->task($first);
        $this->task($second);

        $result = $this->service()->computeForPeople(
            [
                ['id' => $first->id, 'name' => $first->name],
                ['id' => $second->id, 'name' => $second->name],
            ],
            $version,
            self::FROM,
            self::TO,
        );

        $this->assertSame(2, $result['summary']['missing']['quality']);
        $this->assertSame(2, $result['summary']['missing_total']);
    }

    /** Cách 1 không dùng độ khó / chất lượng nên không báo thiếu gì. */
    public function test_base_adjust_reports_no_missing_data(): void
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

        $this->task($user);

        $row = $this->service()->computeForUser($user->id, $user->name, $version, self::FROM, self::TO);

        $this->assertSame(0, $row['missing_total']);
    }
}
