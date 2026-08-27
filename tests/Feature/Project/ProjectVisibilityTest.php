<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Tests\TestCase;

/**
 * Kiểm thử luồng phân quyền xem dự án (mục A):
 *  - user phòng ban A KHÔNG thấy dự án phòng ban B (không liên quan gì)
 *  - user phòng ban A THẤY dự án khi executing_department_id = phòng ban A
 *  - user THẤY dự án khi có mặt trong project_members
 *  - user THẤY dự án khi có mặt trong project_followers
 */
class ProjectVisibilityTest extends TestCase
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

    public function test_user_does_not_see_unrelated_department_project(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $userA = $this->makeUser(['department_id' => $deptA->id], ['team_lead']);
        $creatorB = $this->makeUser(['department_id' => $deptB->id], ['department_director']);

        $unrelated = $this->makeProject([
            'owner_department_id' => $deptB->id,
            'created_by' => $creatorB->id,
        ]);

        $response = $this->actingAs($userA)->getJson('/api/project');
        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();

        $this->assertNotContains($unrelated->id, $ids);
    }

    public function test_user_sees_project_when_executing_department_matches(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $userA = $this->makeUser(['department_id' => $deptA->id], ['team_lead']);
        $creatorB = $this->makeUser(['department_id' => $deptB->id], ['department_director']);

        $delegated = $this->makeProject([
            'owner_department_id' => $deptB->id,
            'executing_department_id' => $deptA->id,
            'created_by' => $creatorB->id,
        ]);
        $delegated->executingDepartments()->sync([$deptA->id]);

        $response = $this->actingAs($userA)->getJson('/api/project');
        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();

        $this->assertContains($delegated->id, $ids);
    }

    public function test_user_sees_project_when_member(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $userA = $this->makeUser(['department_id' => $deptA->id], ['team_lead']);
        $creatorB = $this->makeUser(['department_id' => $deptB->id], ['department_director']);

        $project = $this->makeProject([
            'owner_department_id' => $deptB->id,
            'created_by' => $creatorB->id,
        ]);
        $project->members()->attach($userA->id);

        $response = $this->actingAs($userA)->getJson('/api/project');
        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();

        $this->assertContains($project->id, $ids);
    }

    public function test_user_sees_project_when_follower(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $userA = $this->makeUser(['department_id' => $deptA->id], ['team_lead']);
        $creatorB = $this->makeUser(['department_id' => $deptB->id], ['department_director']);

        $project = $this->makeProject([
            'owner_department_id' => $deptB->id,
            'created_by' => $creatorB->id,
        ]);

        // Theo dõi qua chính API follow (mục B) thay vì thao tác DB trực tiếp
        // để test luôn cả luồng thật.
        $this->actingAs($userA)->postJson("/api/project/{$project->id}/follow")->assertOk();

        $response = $this->actingAs($userA)->getJson('/api/project');
        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();

        $this->assertContains($project->id, $ids);
    }
}
