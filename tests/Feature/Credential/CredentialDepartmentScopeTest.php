<?php

namespace Tests\Feature\Credential;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Services\CredentialService;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

/**
 * Xác nhận credential bị giới hạn theo phòng ban sở hữu (department_id) —
 * yêu cầu gốc: "phòng ban này không được thấy tài khoản của phòng ban kia,
 * trừ khi được cho phép, nếu cho phép thì chỉ nhân viên đó mới thấy được".
 * Xem CredentialService::departmentScopeFor() / CredentialRepository::applyDepartmentScope().
 */
class CredentialDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private Department $deptA;

    private Department $deptB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $this->deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);
    }

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    private function makeCredential(array $attributes = []): Credential
    {
        return Credential::query()->create(array_merge([
            'name' => 'Tài khoản test',
            'account_type' => 'admin',
            'group' => 'external',
            'currency' => 'VND',
        ], $attributes));
    }

    public function test_department_director_only_sees_credentials_of_own_department(): void
    {
        $directorA = $this->makeUser(['department_id' => $this->deptA->id], ['department_director']);

        $credentialA = $this->makeCredential(['department_id' => $this->deptA->id, 'name' => 'Của phòng A']);
        $credentialB = $this->makeCredential(['department_id' => $this->deptB->id, 'name' => 'Của phòng B']);

        $response = $this->actingAs($directorA)->getJson('/api/credential')->assertOk();

        $ids = collect($response->json('credentials'))->pluck('id');
        $this->assertTrue($ids->contains($credentialA->id));
        $this->assertFalse($ids->contains($credentialB->id));
    }

    public function test_department_director_gets_404_viewing_other_department_credential_directly(): void
    {
        $directorA = $this->makeUser(['department_id' => $this->deptA->id], ['department_director']);
        $credentialB = $this->makeCredential(['department_id' => $this->deptB->id]);

        $this->actingAs($directorA)
            ->getJson("/api/credential/{$credentialB->id}")
            ->assertNotFound();
    }

    public function test_department_director_sees_other_department_credential_when_granted_as_viewer(): void
    {
        $directorA = $this->makeUser(['department_id' => $this->deptA->id], ['department_director']);
        $credentialB = $this->makeCredential(['department_id' => $this->deptB->id]);
        $credentialB->viewers()->attach($directorA->id, ['granted_by' => $directorA->id]);

        $response = $this->actingAs($directorA)
            ->getJson("/api/credential/{$credentialB->id}")
            ->assertOk();

        $this->assertSame($credentialB->id, $response->json('credential.id'));

        $listResponse = $this->actingAs($directorA)->getJson('/api/credential')->assertOk();
        $ids = collect($listResponse->json('credentials'))->pluck('id');
        $this->assertTrue($ids->contains($credentialB->id));
    }

    public function test_admin_with_global_credential_manage_sees_every_department(): void
    {
        $admin = $this->makeUser([], ['admin']);

        $this->makeCredential(['department_id' => $this->deptA->id]);
        $this->makeCredential(['department_id' => $this->deptB->id]);

        $response = $this->actingAs($admin)->getJson('/api/credential')->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
    }

    /**
     * department_director chỉ có credential.view (không manage) theo config
     * mặc định nên không gọi được endpoint store() (403) — test hành vi
     * "bị ép về đúng phòng ban mình" trực tiếp qua CredentialService, độc
     * lập với việc role nào thực sự có quyền tạo trong UI thật.
     */
    public function test_creating_credential_without_global_scope_forces_own_department(): void
    {
        $directorA = $this->makeUser(['department_id' => $this->deptA->id], ['department_director']);

        $service = app(CredentialService::class);
        $model = $service->create(['name' => 'Test', 'account_type' => 'admin', 'department_id' => $this->deptB->id], $directorA);

        $this->assertSame($this->deptA->id, $model->department_id);
    }

    public function test_global_manager_can_choose_department_when_creating(): void
    {
        $admin = $this->makeUser(['department_id' => $this->deptA->id], ['admin']);

        $response = $this->actingAs($admin)->postJson('/api/credential', [
            'name' => 'Tài khoản phòng B',
            'account_type' => 'admin',
            'department_id' => $this->deptB->id,
        ])->assertCreated();

        $this->assertSame($this->deptB->id, $response->json('credential.department_id'));
    }
}
