<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

class TaskDueSoonNotificationTest extends TestCase
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

    public function test_task_due_tomorrow_notifies_assignee(): void
    {
        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id, 'name' => 'Quản lý']);
        $assignee = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người thực hiện']);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $manager->id]);
        $this->makeTask($project, [
            'assignee_id' => $assignee->id,
            'manager_id' => $manager->id,
            'end_date' => now()->addDay()->toDateString(),
        ]);

        Artisan::call('project:notify-tasks-due-soon');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $assignee->id,
            'actor_id' => $manager->id,
            'type' => 'task_due_soon',
        ]);
    }

    public function test_task_due_today_notifies_assignee(): void
    {
        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id]);
        $assignee = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $manager->id]);
        $this->makeTask($project, [
            'assignee_id' => $assignee->id,
            'manager_id' => $manager->id,
            'end_date' => now()->toDateString(),
        ]);

        Artisan::call('project:notify-tasks-due-soon');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $assignee->id,
            'type' => 'task_due_soon',
        ]);
    }

    public function test_completed_task_is_not_notified(): void
    {
        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id]);
        $assignee = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $manager->id]);
        $this->makeTask($project, [
            'assignee_id' => $assignee->id,
            'manager_id' => $manager->id,
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'completed',
        ]);

        Artisan::call('project:notify-tasks-due-soon');

        $this->assertSame(0, UserNotification::query()->where('type', 'task_due_soon')->count());
    }

    public function test_task_without_assignee_is_marked_notified_without_sending(): void
    {
        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $manager->id]);
        $task = $this->makeTask($project, [
            'manager_id' => $manager->id,
            'end_date' => now()->addDay()->toDateString(),
        ]);

        Artisan::call('project:notify-tasks-due-soon');

        $this->assertSame(0, UserNotification::query()->where('type', 'task_due_soon')->count());
        $this->assertNotNull($task->fresh()->due_soon_notified_at);
    }

    public function test_running_command_twice_does_not_duplicate_notification(): void
    {
        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id]);
        $assignee = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $manager->id]);
        $this->makeTask($project, [
            'assignee_id' => $assignee->id,
            'manager_id' => $manager->id,
            'end_date' => now()->addDay()->toDateString(),
        ]);

        Artisan::call('project:notify-tasks-due-soon');
        Artisan::call('project:notify-tasks-due-soon');

        $this->assertSame(1, UserNotification::query()->where('type', 'task_due_soon')->count());
    }

    public function test_changing_end_date_resets_due_soon_flag(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $assignee = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $manager->id]);
        $task = $this->makeTask($project, [
            'assignee_id' => $assignee->id,
            'manager_id' => $manager->id,
            'end_date' => now()->addDay()->toDateString(),
        ]);

        Artisan::call('project:notify-tasks-due-soon');
        $this->assertNotNull($task->fresh()->due_soon_notified_at);

        $this->actingAs($manager)->putJson("/api/project/tasks/{$task->id}", [
            'title' => $task->title,
            'end_date' => now()->addDays(10)->toDateString(),
        ])->assertOk();

        $this->assertNull($task->fresh()->due_soon_notified_at);
    }
}
