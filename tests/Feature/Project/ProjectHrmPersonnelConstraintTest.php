<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Tests\TestCase;

class ProjectHrmPersonnelConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.hrm.database' => 'hrm_test_only',
        ]);
    }

    private function makeUser(array $attributes = [], array $roles = ['department_director']): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        return $user;
    }

    public function test_assignable_users_excludes_accounts_without_hrm_link(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create([
            'code' => 'PB-HRM',
            'name' => 'Phòng HRM',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-1',
        ]);

        $linked = $this->makeUser([
            'department_id' => $dept->id,
            'hrm_employee_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);
        $localOnly = $this->makeUser(['department_id' => $dept->id, 'hrm_employee_uuid' => null]);
        $director = $this->makeUser(['department_id' => $dept->id, 'hrm_employee_uuid' => '22222222-2222-4222-8222-222222222222']);

        $response = $this->actingAs($director)->getJson('/api/project/assignable-users');
        $response->assertOk();

        $ids = collect($response->json('users'))->pluck('id')->all();
        $this->assertContains($linked->id, $ids);
        $this->assertNotContains($localOnly->id, $ids);
    }

    public function test_cannot_add_non_hrm_member_to_project(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create([
            'code' => 'PB-HRM',
            'name' => 'Phòng HRM',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-1',
        ]);

        $director = $this->makeUser([
            'department_id' => $dept->id,
            'hrm_employee_uuid' => '22222222-2222-4222-8222-222222222222',
        ]);
        $localOnly = $this->makeUser(['department_id' => $dept->id, 'hrm_employee_uuid' => null]);

        $project = Project::query()->create([
            'code' => 'PRJ-HRM-1',
            'type' => 'internal',
            'name' => 'Dự án thử HRM',
            'progress_method' => 'average',
            'status' => 'planning',
            'importance' => 'important',
            'owner_department_id' => $dept->id,
            'created_by' => $director->id,
        ]);

        $this->actingAs($director)
            ->putJson('/api/project/'.$project->id, [
                'member_ids' => [$localOnly->id],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Chỉ chọn nhân sự đã liên kết với VA-HRM. Không hợp lệ: '.$localOnly->name.'.']);
    }
}
