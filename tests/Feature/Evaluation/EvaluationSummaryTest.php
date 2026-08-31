<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

/**
 * Bảng tổng hợp đánh giá theo kỳ.
 *
 * Trọng tâm là hai thứ dễ sai và khó phát hiện bằng mắt:
 *   - trạng thái đúng hạn / quá hạn phải tính theo mốc cuối kỳ, để mở lại cùng
 *     một kỳ ở hai thời điểm luôn ra cùng kết quả;
 *   - các nhóm đếm là độc lập, không loại trừ nhau.
 */
class EvaluationSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-08-01';

    private const TO = '2026-08-31';

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $user->roles()->sync(Role::query()->whereIn('code', $roles)->pluck('id'));
        }

        return $user;
    }

    /** @return array{dept: Department, director: User, member: User} */
    private function setUpDepartment(): array
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);

        return [
            'dept' => $dept,
            'director' => $this->makeUser(['department_id' => $dept->id], ['department_director']),
            'member' => $this->makeUser(['department_id' => $dept->id], ['member']),
        ];
    }

    /** @param array<string, mixed> $kit */
    private function version(
        Department $dept,
        array $kit = [],
        array $criteria = [],
    ): EvaluationConfigVersion {
        return EvaluationConfigVersion::query()->create([
            'department_id' => $dept->id,
            'version_no' => 1,
            'status' => EvaluationConfigVersion::STATUS_ACTIVE,
            'kit_snapshot' => array_merge([
                'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
                'base_score' => 100,
                'points_per_completed_task' => 0,
                'points_per_incomplete_task' => 0,
                'formula' => EvaluationScoreKit::defaultFormula(),
                'base_adjust_levels' => EvaluationScoreKit::defaultBaseAdjustLevels(),
            ], $kit),
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
            'origin_department_id' => $assignee->department_id,
            'end_date' => '2026-08-20',
            'actual_end_date' => '2026-08-20',
            'progress_type' => 'percent',
            'sort_order' => 0,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function summaryFor(User $actor, array $query = []): array
    {
        return $this->actingAs($actor)
            ->getJson('/api/evaluation/summary?'.http_build_query(array_merge([
                'from' => self::FROM,
                'to' => self::TO,
            ], $query)))
            ->assertOk()
            ->json();
    }

    /** Dòng của đúng một nhân sự trong kết quả tổng hợp. */
    private function rowOf(array $summary, User $user): array
    {
        foreach ($summary['rows'] as $row) {
            if ((int) $row['user_id'] === (int) $user->id) {
                return $row;
            }
        }

        $this->fail('Không tìm thấy dòng của nhân sự trong bảng tổng hợp.');
    }

    /* ---------- Đúng hạn / quá hạn ---------- */

    public function test_task_completed_before_deadline_is_on_time(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        $this->task($member, ['end_date' => '2026-08-20', 'actual_end_date' => '2026-08-19']);

        $row = $this->rowOf($this->summaryFor($director), $member);

        $this->assertSame('on_time', $row['task_breakdown'][0]['on_time_state']);
        $this->assertFalse($row['task_breakdown'][0]['is_overdue']);
        $this->assertSame(1, $row['task_status_counts']['by_timeliness']['on_time']);
    }

    public function test_task_completed_after_deadline_counts_as_both_completed_and_overdue(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        $this->task($member, ['end_date' => '2026-08-10', 'actual_end_date' => '2026-08-11']);

        $row = $this->rowOf($this->summaryFor($director), $member);
        $counts = $row['task_status_counts'];

        // Việc hoàn thành muộn vẫn là việc đã hoàn thành — hai nhóm đếm này
        // độc lập, không được loại trừ nhau.
        $this->assertSame(1, $counts['by_status']['completed']);
        $this->assertSame(1, $counts['by_timeliness']['overdue']);
        $this->assertSame(0, $counts['by_timeliness']['on_time']);
        $this->assertTrue($row['task_breakdown'][0]['is_overdue']);
    }

    public function test_unfinished_task_past_deadline_is_overdue(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        $this->task($member, [
            'status' => 'in_progress',
            'end_date' => '2026-08-10',
            'actual_end_date' => null,
        ]);

        $row = $this->rowOf($this->summaryFor($director), $member);

        $this->assertSame('overdue', $row['task_breakdown'][0]['on_time_state']);
        $this->assertSame(1, $row['task_status_counts']['by_timeliness']['overdue']);
    }

    public function test_unfinished_task_before_period_end_is_not_overdue(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        $this->task($member, [
            'status' => 'in_progress',
            'end_date' => '2026-08-31',
            'actual_end_date' => null,
        ]);

        $row = $this->rowOf($this->summaryFor($director), $member);

        $this->assertSame('on_time', $row['task_breakdown'][0]['on_time_state']);
        $this->assertSame(0, $row['task_status_counts']['by_timeliness']['overdue']);
    }

    public function test_task_without_deadline_is_unknown_and_counted_nowhere(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        // Không có hạn thì không có căn cứ nói đúng hay trễ. Vẫn phải nằm
        // trong kỳ nên đặt ngày hoàn thành thực tế trong khoảng.
        $this->task($member, ['end_date' => null, 'actual_end_date' => '2026-08-15']);

        $row = $this->rowOf($this->summaryFor($director), $member);
        $timeliness = $row['task_status_counts']['by_timeliness'];

        $this->assertSame('unknown', $row['task_breakdown'][0]['on_time_state']);
        $this->assertSame(0, $timeliness['on_time']);
        $this->assertSame(0, $timeliness['overdue']);
        $this->assertSame(1, $timeliness['unknown']);
    }

    /* ---------- Không đổi theo thời gian ---------- */

    public function test_overdue_state_uses_period_end_not_today(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        // Việc chưa xong, hạn 01/08 — nằm NGOÀI kỳ tháng 7 xét theo hạn, nên
        // dùng kỳ tháng 8 và kiểm tra rằng "hôm nay" không ảnh hưởng kết quả.
        $this->task($member, [
            'status' => 'in_progress',
            'end_date' => '2026-08-25',
            'actual_end_date' => null,
        ]);

        // Đứng ở tháng 9 nhìn lại kỳ tháng 8: việc hạn 25/08 chưa xong. Xét
        // theo mốc cuối kỳ (31/08) thì nó quá hạn — và phải quá hạn dù hôm nay
        // là ngày nào đi nữa.
        Carbon::setTestNow('2026-09-30');
        $first = $this->rowOf($this->summaryFor($director), $member);

        Carbon::setTestNow('2027-05-05');
        $second = $this->rowOf($this->summaryFor($director), $member);

        Carbon::setTestNow();

        $this->assertSame('overdue', $first['task_breakdown'][0]['on_time_state']);
        $this->assertSame(
            $first['task_breakdown'][0]['on_time_state'],
            $second['task_breakdown'][0]['on_time_state'],
            'Trạng thái trễ hạn phải giữ nguyên khi mở lại cùng một kỳ ở thời điểm khác.',
        );
        $this->assertSame($first['final_score'], $second['final_score']);
    }

    public function test_future_deadline_is_not_overdue_even_long_after_the_period(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        // Hạn sau ngày cuối kỳ → trong kỳ này chưa thể coi là trễ, kể cả khi
        // hiện tại đã là năm sau.
        $this->task($member, [
            'status' => 'in_progress',
            'end_date' => '2026-08-31',
            'actual_end_date' => null,
        ]);

        Carbon::setTestNow('2027-01-01');
        $row = $this->rowOf($this->summaryFor($director), $member);
        Carbon::setTestNow();

        $this->assertSame('on_time', $row['task_breakdown'][0]['on_time_state']);
    }

    /* ---------- Nhóm đếm ---------- */

    public function test_in_progress_count_excludes_not_started_and_on_hold(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        $this->task($member, ['status' => 'in_progress', 'actual_end_date' => null]);
        $this->task($member, ['status' => 'not_started', 'actual_end_date' => null]);
        $this->task($member, ['status' => 'on_hold', 'actual_end_date' => null]);

        $counts = $this->rowOf($this->summaryFor($director), $member)['task_status_counts'];

        $this->assertSame(3, $counts['total']);
        $this->assertSame(1, $counts['by_status']['in_progress']);
        $this->assertSame(1, $counts['by_status']['not_started']);
        $this->assertSame(1, $counts['by_status']['on_hold']);
    }

    public function test_criterion_totals_group_repeated_events(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động lập kế hoạch', 'score' => 5]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->version($dept);

        foreach (['2026-08-05', '2026-08-12'] as $date) {
            EvaluationEvent::query()->create([
                'department_id' => $dept->id,
                'user_id' => $member->id,
                'criterion_id' => $criterion->id,
                'level_code' => 'A1',
                'level_label' => 'Chủ động lập kế hoạch',
                'score' => 5,
                'occurred_at' => $date,
                'status' => EvaluationEvent::STATUS_APPROVED,
            ]);
        }

        $row = $this->rowOf($this->summaryFor($director), $member);

        // JSON không phân biệt 10 với 10.0 nên so bằng giá trị, không so kiểu.
        $this->assertCount(1, $row['criterion_totals']);
        $this->assertSame(2, $row['criterion_totals'][0]['count']);
        $this->assertEquals(10, $row['criterion_totals'][0]['score']);
        $this->assertEquals(10, $row['bonus']);
    }

    public function test_criteria_columns_follow_snapshot_order(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        // Bản chụp cố tình xếp ngược thứ tự id để chứng minh cột đi theo thứ
        // tự cấu hình, không phải theo id tăng dần.
        $this->version($dept, [], [
            [
                'id' => 90,
                'name' => 'Hợp tác',
                'type' => 'behavior',
                'criterion_type' => ['id' => 1, 'name' => 'Thái độ'],
                'levels' => [['code' => 'B1', 'label' => 'Hỗ trợ đồng nghiệp', 'score' => 3]],
            ],
            [
                'id' => 10,
                'name' => 'Kỷ luật',
                'type' => 'behavior',
                'criterion_type' => ['id' => 1, 'name' => 'Thái độ'],
                'levels' => [['code' => 'C1', 'label' => 'Đi muộn', 'score' => -2]],
            ],
        ]);

        $criteria = $this->summaryFor($director)['criteria'];

        $this->assertSame(['Hợp tác', 'Kỷ luật'], array_column($criteria, 'name'));
        $this->assertSame('Thái độ', $criteria[0]['criterion_type_name']);
        $this->assertSame('B1', $criteria[0]['levels'][0]['code']);
    }

    /* ---------- Ghi nhận tại chỗ ---------- */

    public function test_creating_event_with_period_returns_recomputed_row(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động lập kế hoạch', 'score' => 6]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->version($dept);

        $response = $this->actingAs($director)
            ->postJson('/api/evaluation/events', [
                'user_id' => $member->id,
                'criterion_id' => $criterion->id,
                'level_code' => 'A1',
                'occurred_at' => '2026-08-10',
                'period_from' => self::FROM,
                'period_to' => self::TO,
            ])
            ->assertCreated();

        // Máy chủ trả sẵn dòng đã tính lại — giao diện không phải tự cộng điểm
        // và cũng không phải tải lại cả bảng.
        $response->assertJsonPath('row.bonus', 6);
        $response->assertJsonPath('row.final_score', 106);
        $this->assertNotNull($response->json('row.classification_code'));

        // Và phải khớp đúng với kết quả tổng hợp gọi lại từ đầu.
        $fresh = $this->rowOf($this->summaryFor($director), $member);
        $this->assertSame($fresh['final_score'], $response->json('row.final_score'));
    }

    public function test_creating_event_without_period_keeps_old_response_shape(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động', 'score' => 6]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->version($dept);

        $this->actingAs($director)
            ->postJson('/api/evaluation/events', [
                'user_id' => $member->id,
                'criterion_id' => $criterion->id,
                'level_code' => 'A1',
                'occurred_at' => '2026-08-10',
            ])
            ->assertCreated()
            ->assertJsonMissingPath('row');
    }

    public function test_identical_second_record_is_created_but_flagged(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động', 'score' => 6]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->version($dept);

        $payload = [
            'user_id' => $member->id,
            'criterion_id' => $criterion->id,
            'level_code' => 'A1',
            'occurred_at' => '2026-08-10',
        ];

        $this->actingAs($director)->postJson('/api/evaluation/events', $payload)
            ->assertCreated()
            ->assertJsonMissingPath('duplicate_warning');

        // Lần hai vẫn được ghi — hành vi lặp lại trong ngày là chuyện có thật —
        // nhưng người dùng phải được báo để biết mình vừa tạo bản thứ hai.
        $this->actingAs($director)->postJson('/api/evaluation/events', $payload)
            ->assertCreated()
            ->assertJsonPath('duplicate_warning', true);

        $this->assertSame(2, EvaluationEvent::query()->count());
    }

    /* ---------- Quyền ---------- */

    public function test_member_cannot_view_summary(): void
    {
        ['dept' => $dept, 'member' => $member] = $this->setUpDepartment();
        $this->version($dept);

        $this->actingAs($member)
            ->getJson('/api/evaluation/summary?from='.self::FROM.'&to='.self::TO)
            ->assertForbidden();
    }

    public function test_user_without_department_gets_clear_message(): void
    {
        $this->seed(RoleSeeder::class);
        $orphan = $this->makeUser(['department_id' => null], ['department_director']);

        $this->actingAs($orphan)
            ->getJson('/api/evaluation/summary?from='.self::FROM.'&to='.self::TO)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tài khoản chưa gắn với phòng ban nào.');
    }

    public function test_department_without_published_version_is_told_what_to_do(): void
    {
        ['director' => $director] = $this->setUpDepartment();

        $this->actingAs($director)
            ->getJson('/api/evaluation/summary?from='.self::FROM.'&to='.self::TO)
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');
    }

    public function test_period_must_be_a_valid_range(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();
        $this->version($dept);

        $this->actingAs($director)
            ->getJson('/api/evaluation/summary?from=2026-08-31&to=2026-08-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');

        $this->actingAs($director)
            ->getJson('/api/evaluation/summary?from=2026-01-01&to=2027-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }
}
