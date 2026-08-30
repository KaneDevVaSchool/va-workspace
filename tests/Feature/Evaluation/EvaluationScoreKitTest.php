<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class EvaluationScoreKitTest extends TestCase
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

    private function makeScale(Department $dept, string $name, bool $forTaskType = false): EvaluationCriteria
    {
        return EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => $name,
            'type' => 'scale',
            'levels' => [
                ['code' => 'M1', 'label' => 'Khá', 'score' => 80],
                ['code' => 'M2', 'label' => 'Tốt', 'score' => 90],
            ],
            'is_active' => true,
            'use_for_task_type' => $forTaskType,
            'sort_order' => 0,
        ]);
    }

    public function test_director_gets_default_kit_when_none_saved(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/evaluation/score-kit')
            ->assertOk()
            ->assertJsonPath('kit.department_id', $dept->id)
            ->assertJsonPath('kit.mode', null)
            ->assertJsonPath('kit.base_score', 100)
            ->assertJsonPath('kit.use_project_importance', true)
            ->assertJsonPath('kit.id', null);
    }

    public function test_director_saves_base_adjust_kit(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $scale = $this->makeScale($dept, 'Xếp loại tháng');

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_score' => 100,
                'points_per_completed_task' => 2,
                'points_per_incomplete_task' => -3,
                'classification_criterion_id' => $scale->id,
            ])
            ->assertOk()
            ->assertJsonPath('kit.mode', 'base_adjust')
            ->assertJsonPath('kit.base_score', 100)
            ->assertJsonPath('kit.points_per_completed_task', 2)
            ->assertJsonPath('kit.points_per_incomplete_task', -3)
            ->assertJsonPath('kit.classification_criterion_id', $scale->id);

        $this->assertDatabaseHas('evaluation_score_kits', [
            'department_id' => $dept->id,
            'mode' => 'base_adjust',
        ]);
    }

    public function test_director_saves_weighted_task_kit(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'weighted_task',
                'use_project_importance' => false,
            ])
            ->assertOk()
            ->assertJsonPath('kit.mode', 'weighted_task')
            ->assertJsonPath('kit.use_project_importance', false);
    }

    public function test_rejects_behavior_criterion_as_classification(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $behavior = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Đi muộn',
            'type' => 'behavior',
            'levels' => [
                ['code' => 'H1', 'label' => 'Đi muộn', 'score' => -1],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'classification_criterion_id' => $behavior->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['classification_criterion_id']);
    }

    public function test_rejects_classification_from_other_department(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $other = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $scale = $this->makeScale($other, 'Thang phòng khác');

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'classification_criterion_id' => $scale->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['classification_criterion_id']);
    }

    public function test_member_cannot_view_or_update_kit(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($member)
            ->getJson('/api/evaluation/score-kit')
            ->assertForbidden();

        $this->actingAs($member)
            ->putJson('/api/evaluation/score-kit', ['mode' => 'base_adjust'])
            ->assertForbidden();
    }

    public function test_show_includes_assigned_task_type_criterion(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $scale = $this->makeScale($dept, 'Mức độ quan trọng', true);

        $this->actingAs($director)
            ->getJson('/api/evaluation/score-kit')
            ->assertOk()
            ->assertJsonPath('kit.task_type_criterion_id', $scale->id)
            ->assertJsonPath('kit.task_type_criterion.name', 'Mức độ quan trọng');
    }
}
