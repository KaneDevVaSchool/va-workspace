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
 *  - user THẤY dự án khi có mặt trong project_followers (nhưng phải đã xem
 *    được dự án — thuộc phòng giao/thực hiện/thành viên — mới follow được;
 *    user hoàn toàn ngoài phạm vi bị chặn 404 khi gọi API follow)
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

    /**
     * Là thành viên của dự án phòng ban KHÁC thì vẫn KHÔNG xem được.
     *
     * Quy tắc đã siết lại (commit dff1c55, 22/09/2026): phạm vi xem dự án chỉ
     * mở theo PHÒNG BAN (sở hữu / phụ trách / được giao thực hiện / có scope),
     * không mở theo tư cách cá nhân — xem docblock
     * ProjectRepository::whereViewerDepartment(). Test này trước đây kỳ vọng
     * ngược lại (thành viên thì xem được), tức là đang kiểm hành vi cũ đã bị
     * thay đổi có chủ ý, nên đảo lại cho khớp quy tắc hiện hành. Nó cũng nhất
     * quán với test_user_cannot_follow_unrelated_department_project() ngay dưới.
     */
    public function test_member_of_other_department_project_still_cannot_see_it(): void
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

        $this->assertNotContains($project->id, $ids);
    }

    /** Thành viên cùng phòng ban sở hữu thì xem được — đường đi hợp lệ. */
    public function test_user_sees_project_of_own_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);

        $user = $this->makeUser(['department_id' => $dept->id], ['team_lead']);
        $creator = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/project');
        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();

        $this->assertContains($project->id, $ids);
    }

    public function test_member_can_open_project_list_for_own_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);

        $member = $this->makeUser(['department_id' => $dept->id], ['member']);
        $creator = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($member)->getJson('/api/project');

        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();
        $this->assertContains($project->id, $ids);
    }

    /** Follow dự án phòng ban mình thì được, và vẫn xem được sau đó. */
    public function test_user_can_follow_project_of_own_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);

        $user = $this->makeUser(['department_id' => $dept->id], ['team_lead']);
        $creator = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($user)
            ->postJson("/api/project/{$project->id}/follow")
            ->assertOk()
            ->assertJsonPath('is_following', true);

        $response = $this->actingAs($user)->getJson('/api/project');
        $response->assertOk();
        $ids = collect($response->json('projects'))->pluck('id')->all();

        $this->assertContains($project->id, $ids);
    }

    public function test_user_cannot_follow_unrelated_department_project(): void
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

        // Không thuộc phòng giao/thực hiện, không phải thành viên — không
        // được phép xem, nên cũng không follow được (404, không lộ dữ liệu).
        $this->actingAs($userA)->postJson("/api/project/{$unrelated->id}/follow")->assertNotFound();
    }
}
