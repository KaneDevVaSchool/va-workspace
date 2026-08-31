<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

class PersonnelEvaluationReportTest extends TestCase
{
    use RefreshDatabase;

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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Đánh giá nhân sự tháng 08/2026',
            'period_type' => 'month',
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ], $overrides);
    }

    public function test_director_creates_report_and_version_is_published_automatically(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        $this->assertSame(0, EvaluationConfigVersion::query()->count());

        $response = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->assertJsonPath('report.title', 'Đánh giá nhân sự tháng 08/2026')
            ->assertJsonPath('report.status', 'draft')
            ->assertJsonPath('report.version_no', 1);

        $this->assertSame(1, EvaluationConfigVersion::query()->count());
        $this->assertNotNull($response->json('report.evaluation_config_version_id'));
    }

    public function test_member_cannot_create_report(): void
    {
        ['member' => $member] = $this->setUpDepartment();

        $this->actingAs($member)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertForbidden();
    }

    public function test_report_shows_rows_for_every_department_member(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        $reportId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->json('report.id');

        $response = $this->actingAs($director)
            ->getJson('/api/report/'.$reportId)
            ->assertOk();

        // Cả trưởng phòng và nhân viên đều thuộc phòng ban nên đều có mặt
        $this->assertSame(2, $response->json('summary.total_people'));
        $userIds = collect($response->json('rows'))->pluck('user_id')->all();
        $this->assertContains($member->id, $userIds);
        $this->assertContains($director->id, $userIds);
    }

    public function test_report_keeps_old_scores_after_score_kit_changes(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        // Khung chấm điểm ban đầu: mỗi việc hoàn thành +5
        $this->actingAs($director)->putJson('/api/evaluation/score-kit', [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 5,
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), ['done' => 'add']),
        ])->assertOk();

        Task::query()->create([
            'type' => 'task',
            'title' => 'Việc trong kỳ',
            'status' => 'completed',
            'assignee_id' => $member->id,
            'end_date' => '2026-08-20',
            'actual_end_date' => '2026-08-20',
            'progress_type' => 'percent',
            'sort_order' => 0,
        ]);

        $reportId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'filter_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        $before = $this->actingAs($director)->getJson('/api/report/'.$reportId)->json('rows.0.final_score');
        $this->assertEquals(105.0, $before);

        // Phòng ban đổi cấu hình: mỗi việc hoàn thành thành +50
        $this->actingAs($director)->putJson('/api/evaluation/score-kit', [
            'points_per_completed_task' => 50,
        ])->assertOk();

        // Báo cáo cũ vẫn dùng phiên bản đã chốt lúc tạo nên điểm không đổi
        $after = $this->actingAs($director)->getJson('/api/report/'.$reportId)->json('rows.0.final_score');
        $this->assertEquals(105.0, $after);
    }

    public function test_new_report_uses_newly_published_version(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();

        $this->actingAs($director)->putJson('/api/evaluation/score-kit', [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 5,
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), ['done' => 'add']),
        ])->assertOk();

        Task::query()->create([
            'type' => 'task',
            'title' => 'Việc trong kỳ',
            'status' => 'completed',
            'assignee_id' => $member->id,
            'end_date' => '2026-08-20',
            'actual_end_date' => '2026-08-20',
            'progress_type' => 'percent',
            'sort_order' => 0,
        ]);

        $firstId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'filter_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        // Đổi cấu hình rồi chốt phiên bản mới
        $this->actingAs($director)->putJson('/api/evaluation/score-kit', [
            'points_per_completed_task' => 20,
        ])->assertOk();

        $this->actingAs($director)
            ->postJson('/api/evaluation/config-versions/publish', ['notes' => 'Tăng điểm mỗi việc'])
            ->assertCreated()
            ->assertJsonPath('version.version_no', 2);

        $secondId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'title' => 'Báo cáo tháng 09',
                'filter_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        $old = $this->actingAs($director)->getJson('/api/report/'.$firstId)->json('rows.0.final_score');
        $new = $this->actingAs($director)->getJson('/api/report/'.$secondId)->json('rows.0.final_score');

        $this->assertEquals(105.0, $old);
        $this->assertEquals(120.0, $new);
    }

    public function test_shared_viewer_can_read_but_outsider_cannot(): void
    {
        ['dept' => $dept, 'director' => $director, 'member' => $member] = $this->setUpDepartment();
        $otherDept = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);
        $outsider = $this->makeUser(['department_id' => $otherDept->id], ['member']);

        $reportId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'viewer_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        $this->actingAs($member)->getJson('/api/report/'.$reportId)->assertOk();
        $this->actingAs($outsider)->getJson('/api/report/'.$reportId)->assertForbidden();
    }

    public function test_viewer_cannot_modify_report(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();

        $reportId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'viewer_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        $this->actingAs($member)
            ->putJson('/api/report/'.$reportId, ['title' => 'Đổi tên'])
            ->assertForbidden();

        $this->actingAs($member)
            ->deleteJson('/api/report/'.$reportId)
            ->assertForbidden();
    }

    public function test_employee_detail_lists_task_and_event_breakdown(): void
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

        $this->actingAs($director)->putJson('/api/evaluation/score-kit', [
            'mode' => EvaluationScoreKit::MODE_BASE_ADJUST,
            'base_score' => 100,
            'points_per_completed_task' => 5,
            'formula' => array_merge(EvaluationScoreKit::defaultFormula(), ['done' => 'add']),
        ])->assertOk();

        Task::query()->create([
            'type' => 'task',
            'title' => 'Việc A',
            'status' => 'completed',
            'assignee_id' => $member->id,
            'end_date' => '2026-08-20',
            'actual_end_date' => '2026-08-20',
            'progress_type' => 'percent',
            'sort_order' => 0,
        ]);

        $this->actingAs($director)->postJson('/api/evaluation/events', [
            'user_id' => $member->id,
            'criterion_id' => $criterion->id,
            'level_code' => 'A1',
            'occurred_at' => '2026-08-15',
            'reason' => 'Chủ động lên kế hoạch quý',
        ])->assertCreated();

        $reportId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'filter_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        $detail = $this->actingAs($director)
            ->getJson('/api/report/'.$reportId.'/employees/'.$member->id)
            ->assertOk()
            ->json('detail');

        // 100 điểm gốc + 5 điểm việc + 6 điểm hành vi
        $this->assertEquals(111.0, $detail['final_score']);
        $this->assertCount(1, $detail['task_breakdown']);
        $this->assertCount(1, $detail['event_breakdown']);
        $this->assertSame('Chủ động lập kế hoạch', $detail['event_breakdown'][0]['level_label']);
    }

    public function test_saved_report_keeps_period_unchanged(): void
    {
        ['director' => $director] = $this->setUpDepartment();

        $reportId = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->json('report.id');

        $this->actingAs($director)
            ->patchJson('/api/report/'.$reportId.'/save')
            ->assertOk()
            ->assertJsonPath('report.status', 'saved');

        // Đã lưu thì đổi kỳ không còn tác dụng, nhưng vẫn đổi được tên
        $this->actingAs($director)
            ->putJson('/api/report/'.$reportId, [
                'title' => 'Tên mới',
                'period_from' => '2026-01-01',
                'period_to' => '2026-01-31',
            ])
            ->assertOk()
            ->assertJsonPath('report.title', 'Tên mới')
            ->assertJsonPath('report.period_from', '2026-08-01');
    }

    public function test_report_list_only_shows_permitted_reports(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();
        $otherDept = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);
        $outsider = $this->makeUser(['department_id' => $otherDept->id], ['member']);

        $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload(['viewer_user_ids' => [$member->id]]))
            ->assertCreated();

        $this->actingAs($director)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
        $this->actingAs($member)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
        $this->actingAs($outsider)->getJson('/api/report')->assertOk()->assertJsonCount(0, 'reports');
    }

    /**
     * Giám đốc điều hành giám sát toàn hệ thống nên thấy báo cáo của mọi phòng
     * ban, kể cả phòng mình không thuộc về và không được chia sẻ đích danh.
     */
    public function test_director_officer_sees_reports_of_every_department(): void
    {
        ['director' => $director] = $this->setUpDepartment();
        $otherDept = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);
        $officer = $this->makeUser(['department_id' => $otherDept->id], ['director_officer']);

        $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated();

        $this->actingAs($officer)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
    }
}
