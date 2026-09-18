<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Sprint;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

class ProjectStructureAndBulkTasksTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = ['department_director']): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        return $user;
    }

    private function makeProject(User $owner, array $attributes = []): Project
    {
        return Project::query()->create(array_merge([
            'code' => 'PRJ'.random_int(1000, 999999),
            'type' => 'internal',
            'name' => 'Dự án cấu trúc',
            'progress_method' => 'average',
            'status' => 'planning',
            'importance' => 'important',
            'owner_department_id' => $owner->department_id,
            'created_by' => $owner->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'constrain_task_dates_to_project' => true,
        ], $attributes));
    }

    private function makeTask(Project $project, array $attributes = []): Task
    {
        return Task::query()->create(array_merge([
            'project_id' => $project->id,
            'type' => 'task',
            'title' => 'Công việc thử',
            'status' => 'not_started',
            'created_by' => $project->created_by,
        ], $attributes));
    }

    public function test_show_includes_scope_permission_flags(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);

        $response = $this->actingAs($editor)->getJson('/api/project/'.$project->id);
        $response->assertOk();
        $response->assertJsonPath('project.can_edit', true);
        $response->assertJsonPath('project.can_manage_members', true);
    }

    public function test_bulk_create_returns_all_tasks_in_transaction(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);

        $response = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [
                ['title' => 'Việc A', 'start_date' => '2026-02-01', 'end_date' => '2026-02-10'],
                ['title' => 'Việc B', 'start_date' => '2026-03-01', 'end_date' => '2026-03-10'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'tasks');
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Việc A', 'type' => 'task']);
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Việc B', 'type' => 'task']);
    }

    public function test_bulk_create_rolls_back_when_date_outside_project(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);

        $response = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [
                ['title' => 'Hợp lệ', 'start_date' => '2026-02-01', 'end_date' => '2026-02-10'],
                ['title' => 'Ngoài khoảng', 'start_date' => '2027-01-01', 'end_date' => '2027-01-10'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('tasks', ['project_id' => $project->id, 'title' => 'Hợp lệ']);
        $this->assertDatabaseMissing('tasks', ['project_id' => $project->id, 'title' => 'Ngoài khoảng']);
    }

    public function test_create_task_under_category_and_rejects_wrong_project_parent(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $other = $this->makeProject($editor, ['code' => 'PRJ'.random_int(1000, 999999), 'name' => 'Khác']);

        $category = $this->makeTask($project, ['type' => 'category', 'title' => 'DM 1']);
        $foreignParent = $this->makeTask($other, ['type' => 'category', 'title' => 'DM khác']);

        $ok = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks', [
            'type' => 'task',
            'title' => 'Trong danh mục',
            'parent_id' => $category->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('task.parent_id', $category->id);

        $bad = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks', [
            'type' => 'task',
            'title' => 'Cha sai dự án',
            'parent_id' => $foreignParent->id,
        ]);
        $bad->assertStatus(422);
    }

    public function test_sync_categories_create_reorder_and_block_delete_with_children(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);

        $create = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/structure/category', [
            'items' => [
                ['title' => 'Danh mục A', 'progress_type' => 'average', 'sort_order' => 0],
                ['title' => 'Danh mục B', 'progress_type' => 'task_weighted', 'sort_order' => 1],
            ],
            'deleted_ids' => [],
        ]);
        $create->assertOk();
        $create->assertJsonCount(2, 'tasks');

        $catA = Task::query()->where('project_id', $project->id)->where('title', 'Danh mục A')->firstOrFail();
        $catB = Task::query()->where('project_id', $project->id)->where('title', 'Danh mục B')->firstOrFail();
        $this->makeTask($project, ['type' => 'task', 'title' => 'Con của A', 'parent_id' => $catA->id]);

        $reorder = $this->actingAs($editor)->putJson('/api/project/'.$project->id.'/structure/category', [
            'items' => [
                ['id' => $catB->id, 'title' => 'Danh mục B', 'progress_type' => 'task_weighted', 'sort_order' => 0],
                ['id' => $catA->id, 'title' => 'Danh mục A đổi tên', 'progress_type' => 'average', 'sort_order' => 1],
            ],
            'deleted_ids' => [],
        ]);
        $reorder->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $catA->id, 'title' => 'Danh mục A đổi tên', 'sort_order' => 1]);
        $this->assertDatabaseHas('tasks', ['id' => $catB->id, 'sort_order' => 0]);

        $blocked = $this->actingAs($editor)->putJson('/api/project/'.$project->id.'/structure/category', [
            'items' => [
                ['id' => $catB->id, 'title' => 'Danh mục B', 'progress_type' => 'task_weighted', 'sort_order' => 0],
            ],
            'deleted_ids' => [$catA->id],
        ]);
        $blocked->assertStatus(422);
        $this->assertDatabaseHas('tasks', ['id' => $catA->id]);
    }

    public function test_sync_phases_respects_project_date_window(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);

        $bad = $this->actingAs($editor)->putJson('/api/project/'.$project->id.'/structure/phase', [
            'items' => [
                [
                    'title' => 'Phase ngoài',
                    'progress_type' => 'average',
                    'start_date' => '2025-01-01',
                    'end_date' => '2025-02-01',
                    'sort_order' => 0,
                ],
            ],
            'deleted_ids' => [],
        ]);
        $bad->assertStatus(422);

        $ok = $this->actingAs($editor)->putJson('/api/project/'.$project->id.'/structure/phase', [
            'items' => [
                [
                    'title' => 'Phase 1',
                    'progress_type' => 'duration_weighted',
                    'start_date' => '2026-01-15',
                    'end_date' => '2026-03-15',
                    'description' => 'Giai đoạn khởi động',
                    'sort_order' => 0,
                ],
            ],
            'deleted_ids' => [],
        ]);
        $ok->assertOk();
        $ok->assertJsonPath('tasks.0.type', 'phase');
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'type' => 'phase',
            'title' => 'Phase 1',
        ]);
    }

    public function test_bulk_forbidden_without_task_create_permission(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);
        $project = $this->makeProject($member);

        $this->actingAs($member)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [['title' => 'Không có quyền']],
        ])->assertForbidden();
    }

    public function test_bulk_create_assigns_sprint_and_rejects_foreign_sprint(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $other = $this->makeProject($editor, ['code' => 'PRJ'.random_int(1000, 999999), 'name' => 'Khác']);

        $phase = $this->makeTask($project, [
            'type' => 'phase',
            'title' => 'Phase 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $sprint = Sprint::query()->create([
            'project_id' => $project->id,
            'phase_id' => $phase->id,
            'name' => 'Sprint 1',
            'status' => 'planned',
            'created_by' => $editor->id,
        ]);
        $foreignPhase = $this->makeTask($other, [
            'type' => 'phase',
            'title' => 'Phase khác',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $foreignSprint = Sprint::query()->create([
            'project_id' => $other->id,
            'phase_id' => $foreignPhase->id,
            'name' => 'Sprint khác',
            'status' => 'planned',
            'created_by' => $editor->id,
        ]);

        $ok = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [
                [
                    'title' => 'Việc sprint',
                    'sprint_id' => $sprint->id,
                    'start_date' => '2026-02-01',
                    'end_date' => '2026-02-10',
                ],
            ],
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('tasks.0.sprint_id', $sprint->id);
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Việc sprint',
            'sprint_id' => $sprint->id,
        ]);

        $bad = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [
                ['title' => 'Sprint sai dự án', 'sprint_id' => $foreignSprint->id],
            ],
        ]);
        $bad->assertStatus(422);
        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Sprint sai dự án',
        ]);
    }

    public function test_bulk_create_can_nest_subtask_under_parent_in_sprint(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $phase = $this->makeTask($project, [
            'type' => 'phase',
            'title' => 'Phase 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $sprint = Sprint::query()->create([
            'project_id' => $project->id,
            'phase_id' => $phase->id,
            'name' => 'Sprint 1',
            'status' => 'planned',
            'created_by' => $editor->id,
        ]);
        $parent = $this->makeTask($project, [
            'title' => 'Việc cha',
            'sprint_id' => $sprint->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-20',
        ]);

        $response = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [
                [
                    'title' => 'Việc con',
                    'parent_id' => $parent->id,
                    'sprint_id' => $sprint->id,
                    'start_date' => '2026-02-02',
                    'end_date' => '2026-02-10',
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tasks.0.parent_id', $parent->id);
        $response->assertJsonPath('tasks.0.sprint_id', $sprint->id);
        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Việc con',
            'parent_id' => $parent->id,
            'sprint_id' => $sprint->id,
        ]);
    }

    public function test_bulk_create_child_accepts_priority_and_weight(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $parent = $this->makeTask($project, ['title' => 'Việc cha', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28']);

        $response = $this->actingAs($editor)->postJson('/api/project/'.$project->id.'/tasks/bulk', [
            'items' => [
                [
                    'title' => 'Việc nhỏ có độ khó',
                    'parent_id' => $parent->id,
                    'priority' => 'high_priority',
                    'weight' => 40,
                    'start_date' => '2026-02-02',
                    'end_date' => '2026-02-10',
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tasks.0.priority', 'high_priority');
        $this->assertEquals(40.0, (float) $response->json('tasks.0.weight'));
        $this->assertDatabaseHas('tasks', [
            'title' => 'Việc nhỏ có độ khó',
            'parent_id' => $parent->id,
            'priority' => 'high_priority',
        ]);
    }

    public function test_show_parent_includes_child_status_weight_and_priority(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $parent = $this->makeTask($project, ['title' => 'Việc cha', 'status' => 'in_progress']);
        $this->makeTask($project, [
            'title' => 'Việc nhỏ',
            'parent_id' => $parent->id,
            'status' => 'completed',
            'priority' => 'important',
            'weight' => 35,
            'assignee_id' => $editor->id,
        ]);

        $response = $this->actingAs($editor)->getJson('/api/project/tasks/'.$parent->id);

        $response->assertOk();
        $response->assertJsonPath('task.children.0.title', 'Việc nhỏ');
        $response->assertJsonPath('task.children.0.status', 'completed');
        $response->assertJsonPath('task.children.0.priority', 'important');
        $response->assertJsonPath('task.children.0.assignee_id', $editor->id);
        $this->assertEquals(35.0, (float) $response->json('task.children.0.weight'));
    }

    public function test_show_child_includes_parent_status_and_priority(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $parent = $this->makeTask($project, [
            'title' => 'Việc cha',
            'status' => 'in_progress',
            'priority' => 'strategic',
        ]);
        $child = $this->makeTask($project, [
            'title' => 'Việc con',
            'parent_id' => $parent->id,
            'priority' => 'important',
        ]);

        $response = $this->actingAs($editor)->getJson('/api/project/tasks/'.$child->id);

        $response->assertOk();
        $response->assertJsonPath('task.parent.id', $parent->id);
        $response->assertJsonPath('task.parent.title', 'Việc cha');
        $response->assertJsonPath('task.parent.status', 'in_progress');
        $response->assertJsonPath('task.parent.priority', 'strategic');
        $response->assertJsonPath('task.parent.priority_label', 'Chiến lược / Sống còn');
    }

    public function test_update_child_priority(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $editor = $this->makeUser(['department_id' => $dept->id]);
        $project = $this->makeProject($editor);
        $parent = $this->makeTask($project, ['title' => 'Việc cha']);
        $child = $this->makeTask($project, [
            'title' => 'Việc con',
            'parent_id' => $parent->id,
            'priority' => 'support',
        ]);

        $response = $this->actingAs($editor)->putJson('/api/project/tasks/'.$child->id, [
            'priority' => 'high_priority',
        ]);

        $response->assertOk();
        $response->assertJsonPath('task.priority', 'high_priority');
        $this->assertDatabaseHas('tasks', [
            'id' => $child->id,
            'parent_id' => $parent->id,
            'priority' => 'high_priority',
        ]);
    }
}
