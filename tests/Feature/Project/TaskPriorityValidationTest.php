<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Enums\TaskEnums;
use Tests\TestCase;

class TaskPriorityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_priority_keeps_department_level_codes(): void
    {
        $this->assertSame('support', TaskEnums::normalizePriority('B1-1'));
        $this->assertSame('important', TaskEnums::normalizePriority('important'));
        $this->assertSame('CV-A', TaskEnums::normalizePriority('CV-A'));
        $this->assertNull(TaskEnums::normalizePriority(''));
    }

    public function test_create_accepts_department_task_type_level_code(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $user = $this->makeDirector($dept);

        EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Loại công việc phòng A',
            'type' => 'scale',
            'levels' => [
                ['code' => 'CV-A', 'label' => 'Việc thường', 'description' => 'Mức 1', 'score' => 1],
                ['code' => 'CV-B', 'label' => 'Việc trọng tâm', 'description' => 'Mức 2', 'score' => 2],
            ],
            'is_active' => true,
            'use_for_task_type' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/project/tasks', [
            'title' => 'Soạn đề kiểm tra',
            'start_date' => '2026-08-30',
            'end_date' => '2026-09-05',
            'priority' => 'CV-A',
            'type' => 'task',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('task.priority', 'CV-A');
        $response->assertJsonPath('task.priority_label', 'Việc thường');
        $this->assertDatabaseHas('tasks', [
            'title' => 'Soạn đề kiểm tra',
            'priority' => 'CV-A',
        ]);
    }

    public function test_create_still_accepts_canonical_priority(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);
        $user = $this->makeDirector($dept);

        $response = $this->actingAs($user)->postJson('/api/project/tasks', [
            'title' => 'Việc chuẩn',
            'start_date' => '2026-08-30',
            'end_date' => '2026-09-05',
            'priority' => 'important',
            'type' => 'task',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('task.priority', 'important');
    }

    public function test_create_rejects_unknown_priority(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'C', 'name' => 'Phòng C', 'is_active' => true]);
        $user = $this->makeDirector($dept);

        $response = $this->actingAs($user)->postJson('/api/project/tasks', [
            'title' => 'Việc sai mức',
            'start_date' => '2026-08-30',
            'end_date' => '2026-09-05',
            'priority' => 'not-a-real-level',
            'type' => 'task',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
        $this->assertSame(
            'Mức độ ưu tiên không hợp lệ.',
            $response->json('errors.priority.0'),
        );
    }

    public function test_create_accepts_kit_default_difficulty_code(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'D', 'name' => 'Phòng D', 'is_active' => true]);
        $user = $this->makeDirector($dept);

        EvaluationScoreKit::query()->create([
            'department_id' => $dept->id,
            'mode' => EvaluationScoreKit::MODE_WEIGHTED_TASK,
            'difficulty_use_default' => true,
        ]);

        $options = $this->actingAs($user)
            ->getJson('/api/project/tasks/options')
            ->assertOk()
            ->assertJsonPath('source', 'kit_difficulty')
            ->assertJsonPath('importance.0.value', 'RK');

        $this->assertSame('Rất khó', $options->json('importance.0.label'));

        $this->actingAs($user)->postJson('/api/project/tasks', [
            'title' => 'Soạn đề thi',
            'start_date' => '2026-08-30',
            'end_date' => '2026-09-05',
            'priority' => 'RK',
            'type' => 'task',
        ])
            ->assertCreated()
            ->assertJsonPath('task.priority', 'RK');
    }

    private function makeDirector(Department $dept): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'department_id' => $dept->id,
        ]);
        $roleIds = Role::query()->whereIn('code', ['department_director'])->pluck('id');
        $user->roles()->sync($roleIds);

        return $user;
    }
}
