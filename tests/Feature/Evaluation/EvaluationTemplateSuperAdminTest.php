<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationTemplate;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class EvaluationTemplateSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    private function makeCriterion(Department $dept, string $name = 'Thái độ làm việc'): EvaluationCriteria
    {
        return EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => $name,
            'type' => 'scale',
            'levels' => [
                ['code' => 'M1', 'label' => 'Đạt', 'score' => 1],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function templatePayload(int $criterionId, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mẫu dùng chung toàn hệ thống',
            'description' => 'Superadmin tạo',
            'is_active' => true,
            'is_global' => true,
            'criteria' => [
                [
                    'evaluation_criteria_id' => $criterionId,
                    'weight_percent' => 100,
                    'required_score' => null,
                    'count_in_total' => true,
                ],
            ],
            'position_ids' => [],
            'custom_fields' => [],
        ], $overrides);
    }

    public function test_superadmin_without_department_lists_all_templates_and_creates_global(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $super = $this->makeUser(['department_id' => null], ['super_admin']);
        $criterion = $this->makeCriterion($dept);

        $departmentTemplate = $this->actingAs($director)
            ->postJson('/api/evaluation/templates', $this->templatePayload($criterion->id, [
                'name' => 'Mẫu phòng Nhân sự',
                'is_global' => false,
            ]))
            ->assertCreated()
            ->json('template');

        $this->actingAs($super)
            ->getJson('/api/evaluation/templates')
            ->assertOk()
            ->assertJsonCount(1, 'templates')
            ->assertJsonPath('templates.0.id', $departmentTemplate['id']);

        $created = $this->actingAs($super)
            ->postJson('/api/evaluation/templates', $this->templatePayload($criterion->id))
            ->assertCreated()
            ->json('template');

        $this->assertTrue($created['is_global']);
        $this->assertNull($created['department']);
        $stored = EvaluationTemplate::query()->find($created['id']);
        $this->assertTrue($stored->is_global);
        $this->assertNull($stored->department_id);

        $this->actingAs($super)
            ->getJson('/api/evaluation/templates/global-criteria-pool')
            ->assertOk()
            ->assertJsonPath('criteria.0.id', $criterion->id);

        $this->actingAs($super)
            ->getJson('/api/evaluation/positions')
            ->assertOk();

        $this->actingAs($super)
            ->putJson('/api/evaluation/templates/'.$created['id'], [
                'name' => 'Mẫu dùng chung (đã sửa)',
                'criteria' => $this->templatePayload($criterion->id)['criteria'],
            ])
            ->assertOk()
            ->assertJsonPath('template.name', 'Mẫu dùng chung (đã sửa)');

        $this->actingAs($super)
            ->putJson('/api/evaluation/templates/'.$departmentTemplate['id'], [
                'name' => 'Không được sửa mẫu phòng ban',
            ])
            ->assertForbidden();
    }

    public function test_director_can_still_create_department_template(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $criterion = $this->makeCriterion($dept, 'Kỹ năng chuyên môn');

        $created = $this->actingAs($director)
            ->postJson('/api/evaluation/templates', $this->templatePayload($criterion->id, [
                'name' => 'Mẫu CNTT',
                'is_global' => false,
            ]))
            ->assertCreated()
            ->json('template');

        $this->assertFalse($created['is_global']);
        $this->assertSame($dept->id, $created['department']['id']);
    }

    public function test_superadmin_cannot_unglobal_system_template(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $super = $this->makeUser(['department_id' => null], ['super_admin']);
        $criterion = $this->makeCriterion($dept);

        $created = $this->actingAs($super)
            ->postJson('/api/evaluation/templates', $this->templatePayload($criterion->id))
            ->assertCreated()
            ->json('template');

        $this->actingAs($super)
            ->patchJson('/api/evaluation/templates/'.$created['id'].'/toggle-global')
            ->assertStatus(422);

        $this->assertTrue(EvaluationTemplate::query()->find($created['id'])->is_global);
    }
}
