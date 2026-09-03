<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
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

    public function test_create_stores_original_display_revision(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động', 'score' => 3]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'criterion_ids' => [$criterion->id],
                'column_keys' => ['tasks', 'final_score', 'classification'],
            ]))
            ->assertCreated()
            ->assertJsonPath('report.revision', '1.0')
            ->assertJsonPath('report.display.revision', '1.0')
            ->assertJsonPath('report.display.kind', 'original')
            ->assertJsonPath('report.display.criterion_ids.0', $criterion->id);

        $this->assertSame([$criterion->id], $response->json('report.criterion_ids'));
        $this->assertSame(['tasks', 'final_score', 'classification'], $response->json('report.columns'));
        $this->assertCount(1, $response->json('report.display.revisions'));
    }

    public function test_changing_criteria_saves_appendix_1_1_and_keeps_1_0(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        $first = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động', 'score' => 3]],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $second = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Hợp tác',
            'type' => 'behavior',
            'levels' => [['code' => 'B1', 'label' => 'Hợp tác', 'score' => 2]],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $created = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'criterion_ids' => [$first->id],
            ]))
            ->assertCreated();

        $id = (int) $created->json('report.id');

        $updated = $this->actingAs($director)
            ->patchJson('/api/report/'.$id.'/display', [
                'criterion_ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonPath('report.revision', '1.1')
            ->assertJsonPath('report.display.kind', 'appendix');

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $updated->json('report.display.criterion_ids'),
        );
        $this->assertCount(2, $updated->json('report.display.revisions'));
        $this->assertSame('1.0', $updated->json('report.display.revisions.0.revision'));
        $this->assertSame('original', $updated->json('report.display.revisions.0.kind'));
        $this->assertSame([$first->id], $updated->json('report.display.revisions.0.criterion_ids'));
        $this->assertSame('1.1', $updated->json('report.display.revisions.1.revision'));
        $this->assertSame('appendix', $updated->json('report.display.revisions.1.kind'));

        $this->actingAs($director)
            ->patchJson('/api/report/'.$id.'/display', [
                'criterion_ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonPath('report.revision', '1.1')
            ->assertJsonCount(2, 'report.display.revisions');

        $this->actingAs($director)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
    }

    public function test_member_cannot_save_display_appendix(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();

        $id = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->json('report.id');

        $this->actingAs($member)
            ->patchJson('/api/report/'.$id.'/display', ['criterion_ids' => [1]])
            ->assertForbidden();
    }

    public function test_summary_returns_report_display_mapping(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động', 'score' => 3]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $id = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'criterion_ids' => [$criterion->id],
            ]))
            ->assertCreated()
            ->json('report.id');

        $this->actingAs($director)
            ->getJson('/api/evaluation/summary?from=2026-08-01&to=2026-08-31&report='.$id)
            ->assertOk()
            ->assertJsonPath('report.id', $id)
            ->assertJsonPath('report.revision', '1.0')
            ->assertJsonPath('report.status', 'draft')
            ->assertJsonPath('report.criterion_ids.0', $criterion->id);
    }

    public function test_save_report_changes_status_and_locks_period(): void
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

        EvaluationConfigVersion::query()->create([
            'department_id' => $dept->id,
            'version_no' => 1,
            'status' => EvaluationConfigVersion::STATUS_ACTIVE,
            'published_at' => now(),
            'published_by' => $director->id,
            'kit_snapshot' => ['mode' => 'base_adjust'],
            'criteria_snapshot' => [],
        ]);

        $id = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->json('report.id');

        $this->actingAs($director)
            ->patchJson('/api/report/'.$id.'/save')
            ->assertOk()
            ->assertJsonPath('report.status', 'saved');

        $this->actingAs($director)
            ->getJson('/api/evaluation/summary?from=2026-08-01&to=2026-08-31&report='.$id)
            ->assertOk()
            ->assertJsonPath('report.status', 'saved')
            ->assertJsonPath('period_lock.locked', true);

        $this->actingAs($director)
            ->postJson('/api/evaluation/events', [
                'user_id' => $member->id,
                'criterion_id' => $criterion->id,
                'level_code' => 'A1',
                'occurred_at' => '2026-08-10',
            ])
            ->assertStatus(422);
    }

    public function test_cannot_change_display_after_save(): void
    {
        ['director' => $director] = $this->setUpDepartment();

        $id = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->json('report.id');

        $this->actingAs($director)->patchJson('/api/report/'.$id.'/save')->assertOk();

        $this->actingAs($director)
            ->patchJson('/api/report/'.$id.'/display', ['criterion_ids' => [1]])
            ->assertStatus(422);
    }

    public function test_cannot_delete_saved_report(): void
    {
        ['director' => $director] = $this->setUpDepartment();

        $id = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->json('report.id');

        $this->actingAs($director)->patchJson('/api/report/'.$id.'/save')->assertOk();

        $this->actingAs($director)
            ->deleteJson('/api/report/'.$id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report'])
            ->assertJsonPath('errors.report.0', 'Báo cáo đã lưu, không xoá được.');

        $this->assertDatabaseHas('reports', ['id' => $id, 'status' => 'saved']);
    }
}
