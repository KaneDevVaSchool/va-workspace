<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

class TaskBulkDelegateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = ['department_director']): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        return $user;
    }

    private function makeProject(array $attributes = []): Project
    {
        return Project::query()->create(array_merge([
            'code' => 'PRJ'.random_int(1000, 999999),
            'type' => 'internal',
            'name' => 'Dự án thử nghiệm',
            'progress_method' => 'average',
            'status' => 'planning',
            'importance' => 'important',
        ], $attributes));
    }

    private function makeTask(Project $project, array $attributes = []): Task
    {
        return Task::query()->create(array_merge([
            'project_id' => $project->id,
            'type' => 'task',
            'title' => 'Công việc thử nghiệm',
            'status' => 'not_started',
        ], $attributes));
    }

    public function test_delegate_updates_assignee_and_sets_pending(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $editor = $this->makeUser(['department_id' => $deptA->id, 'name' => 'Người giao']);
        $recipient = $this->makeUser(['department_id' => $deptB->id, 'name' => 'Người nhận'], ['member']);

        $project = $this->makeProject([
            'owner_department_id' => $deptA->id,
            'created_by' => $editor->id,
        ]);
        $task = $this->makeTask($project, [
            'title' => 'Việc chuyển giao',
            'assignee_id' => $editor->id,
        ]);

        $response = $this->actingAs($editor)->patchJson('/api/project/tasks/bulk-delegate', [
            'task_ids' => [$task->id],
            'delegated_to_employee_id' => $recipient->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('tasks.0.assignee_id', $recipient->id);
        $response->assertJsonPath('tasks.0.delegated_to_employee_id', $recipient->id);
        $response->assertJsonPath('tasks.0.delegation_status', 'pending');
        $response->assertJsonPath('tasks.0.origin_department.id', $deptA->id);
        $response->assertJsonPath('tasks.0.delegated_to_department.id', $deptB->id);
        $response->assertJsonPath('tasks.0.delegated_to_employee.id', $recipient->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $recipient->id,
            'origin_department_id' => $deptA->id,
            'delegated_to_department_id' => $deptB->id,
            'delegated_to_employee_id' => $recipient->id,
            'delegation_status' => 'pending',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $recipient->id,
            'actor_id' => $editor->id,
            'type' => 'task_delegated',
            'title' => 'Bạn được chuyển giao 1 công việc',
        ]);
    }

    public function test_delegate_forbidden_without_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);
        $recipient = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người nhận'], ['member']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $member->id,
        ]);
        $task = $this->makeTask($project, ['assignee_id' => $member->id]);

        $this->actingAs($member)->patchJson('/api/project/tasks/bulk-delegate', [
            'task_ids' => [$task->id],
            'delegated_to_employee_id' => $recipient->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $member->id,
            'delegation_status' => null,
        ]);
    }

    public function test_delegate_silently_skips_tasks_outside_viewer_scope(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $editor = $this->makeUser(['department_id' => $deptA->id]);
        $recipient = $this->makeUser(['department_id' => $deptB->id, 'name' => 'Người nhận'], ['member']);
        $otherOwner = $this->makeUser(['department_id' => $deptB->id], ['department_director']);

        $inScope = $this->makeProject([
            'owner_department_id' => $deptA->id,
            'created_by' => $editor->id,
        ]);
        $outOfScope = $this->makeProject([
            'owner_department_id' => $deptB->id,
            'created_by' => $otherOwner->id,
        ]);

        $visibleTask = $this->makeTask($inScope, ['title' => 'Trong phạm vi', 'assignee_id' => $editor->id]);
        $hiddenTask = $this->makeTask($outOfScope, ['title' => 'Ngoài phạm vi', 'assignee_id' => $otherOwner->id]);

        $response = $this->actingAs($editor)->patchJson('/api/project/tasks/bulk-delegate', [
            'task_ids' => [$visibleTask->id, $hiddenTask->id],
            'delegated_to_employee_id' => $recipient->id,
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('tasks'));
        $this->assertSame($visibleTask->id, $response->json('tasks.0.id'));

        $this->assertDatabaseHas('tasks', [
            'id' => $visibleTask->id,
            'assignee_id' => $recipient->id,
            'delegation_status' => 'pending',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $hiddenTask->id,
            'assignee_id' => $otherOwner->id,
            'delegation_status' => null,
        ]);
    }

    public function test_delegate_rejects_unknown_recipient(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $editor->id,
        ]);
        $task = $this->makeTask($project, ['assignee_id' => $editor->id]);

        $this->actingAs($editor)->patchJson('/api/project/tasks/bulk-delegate', [
            'task_ids' => [$task->id],
            'delegated_to_employee_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['delegated_to_employee_id']);
    }
}
