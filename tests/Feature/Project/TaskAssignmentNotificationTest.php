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

class TaskAssignmentNotificationTest extends TestCase
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

    public function test_creating_task_with_assignee_notifies_them(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $creator = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người giao']);
        $assignee = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người nhận'], ['member']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->postJson("/api/project/{$project->id}/tasks", [
            'type' => 'task',
            'title' => 'Việc mới',
            'assignee_id' => $assignee->id,
        ])->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $assignee->id,
            'actor_id' => $creator->id,
            'type' => 'task_assigned',
            'title' => 'Bạn được giao công việc mới',
        ]);
    }

    public function test_creator_is_not_notified_when_assigning_task_to_self(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $creator = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->postJson("/api/project/{$project->id}/tasks", [
            'type' => 'task',
            'title' => 'Tự giao cho mình',
            'assignee_id' => $creator->id,
        ])->assertCreated();

        $this->assertSame(0, \Modules\Identity\App\Models\UserNotification::query()
            ->where('type', 'task_assigned')
            ->count());
    }

    public function test_no_notification_when_task_created_without_assignee(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $creator = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->postJson("/api/project/{$project->id}/tasks", [
            'type' => 'task',
            'title' => 'Chưa có người nhận',
        ])->assertCreated();

        $this->assertSame(0, \Modules\Identity\App\Models\UserNotification::query()
            ->where('type', 'task_assigned')
            ->count());
    }

    public function test_updating_assignee_notifies_the_new_assignee_only(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $oldAssignee = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người cũ'], ['member']);
        $newAssignee = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người mới'], ['member']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $editor->id,
        ]);
        $task = $this->makeTask($project, ['assignee_id' => $oldAssignee->id]);

        $this->actingAs($editor)->putJson("/api/project/tasks/{$task->id}", [
            'title' => $task->title,
            'assignee_id' => $newAssignee->id,
        ])->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $newAssignee->id,
            'type' => 'task_assigned',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $oldAssignee->id,
            'type' => 'task_assigned',
        ]);
    }

    public function test_updating_task_without_changing_assignee_does_not_notify(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $assignee = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người nhận'], ['member']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $editor->id,
        ]);
        $task = $this->makeTask($project, ['assignee_id' => $assignee->id]);

        $this->actingAs($editor)->putJson("/api/project/tasks/{$task->id}", [
            'title' => 'Đổi tên nhưng giữ nguyên người nhận',
            'assignee_id' => $assignee->id,
        ])->assertOk();

        $this->assertSame(0, \Modules\Identity\App\Models\UserNotification::query()
            ->where('type', 'task_assigned')
            ->count());
    }
}
