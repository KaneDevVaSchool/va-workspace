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

class TaskListTabsTest extends TestCase
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

    public function test_index_returns_tab_counts_and_filters_by_tab(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $viewer = $this->makeUser(['department_id' => $dept->id]);
        $other = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người khác']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $viewer->id,
        ]);

        $this->makeTask($project, ['title' => 'Đang làm', 'status' => 'in_progress', 'assignee_id' => $viewer->id]);
        $this->makeTask($project, ['title' => 'Xong rồi', 'status' => 'completed', 'assignee_id' => $other->id]);
        $this->makeTask($project, ['title' => 'Chưa bắt đầu', 'status' => 'not_started']);

        $index = $this->actingAs($viewer)->getJson('/api/project/tasks');
        $index->assertOk();
        $index->assertJsonPath('tab_counts.all', 3);
        $index->assertJsonPath('tab_counts.in_progress', 1);
        $index->assertJsonPath('tab_counts.completed', 1);
        $index->assertJsonPath('tab_counts.not_started', 1);
        $index->assertJsonPath('tab_counts.my_tasks', 1);

        $inProgress = $this->actingAs($viewer)->getJson('/api/project/tasks?tab=in_progress');
        $inProgress->assertOk();
        $this->assertCount(1, $inProgress->json('tasks'));
        $this->assertSame('Đang làm', $inProgress->json('tasks.0.title'));

        $mine = $this->actingAs($viewer)->getJson('/api/project/tasks?tab=my_tasks');
        $mine->assertOk();
        $this->assertCount(1, $mine->json('tasks'));
        $this->assertSame('Đang làm', $mine->json('tasks.0.title'));
    }
}
