<?php

namespace Tests\Feature\Contract;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contract\App\Enums\ContractEnums;
use Modules\Contract\App\Models\Contract;
use Modules\Contract\App\Models\Supplier;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = ['department_director']): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        return $user;
    }

    public function test_department_manager_only_sees_suppliers_in_department(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $deptA->id]);

        Supplier::query()->create([
            'code' => 'NCC-0001',
            'name' => 'Nhà cung cấp A',
            'department_id' => $deptA->id,
            'owner_user_id' => $manager->id,
            'status' => ContractEnums::SUPPLIER_DRAFT,
        ]);
        Supplier::query()->create([
            'code' => 'NCC-0002',
            'name' => 'Nhà cung cấp B',
            'department_id' => $deptB->id,
            'status' => ContractEnums::SUPPLIER_DRAFT,
        ]);

        $response = $this->actingAs($manager)->getJson('/api/contract/suppliers');

        $response->assertOk();
        $this->assertSame(['NCC-0001'], collect($response->json('suppliers'))->pluck('code')->all());
        $response->assertJsonPath('status_counts.all', 1);
    }

    public function test_director_officer_can_view_all_departments(): void
    {
        $this->seed(RoleSeeder::class);

        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $deptA->id], ['director_officer']);

        Supplier::query()->create(['code' => 'NCC-0001', 'name' => 'Nhà cung cấp A', 'department_id' => $deptA->id]);
        Supplier::query()->create(['code' => 'NCC-0002', 'name' => 'Nhà cung cấp B', 'department_id' => $deptB->id]);

        $response = $this->actingAs($director)->getJson('/api/contract/suppliers');

        $response->assertOk();
        $this->assertEqualsCanonicalizing(['NCC-0001', 'NCC-0002'], collect($response->json('suppliers'))->pluck('code')->all());
    }

    public function test_tax_code_duplicate_is_detected_without_spaces(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id]);

        $create = $this->actingAs($manager)->postJson('/api/contract/suppliers', [
            'name' => 'Công ty A',
            'tax_code' => '0313 482 917',
        ]);
        $create->assertCreated();

        $check = $this->actingAs($manager)->getJson('/api/contract/suppliers/check-tax-code?tax_code=0313482917');

        $check->assertOk()
            ->assertJsonPath('duplicate.code', $create->json('supplier.code'));

        $this->actingAs($manager)->postJson('/api/contract/suppliers', [
            'name' => 'Công ty B',
            'tax_code' => '0313482917',
        ])->assertStatus(422);
    }

    public function test_cannot_delete_supplier_with_contract(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $manager = $this->makeUser(['department_id' => $dept->id]);
        $supplier = Supplier::query()->create([
            'code' => 'NCC-0001',
            'name' => 'Nhà cung cấp A',
            'department_id' => $dept->id,
            'status' => ContractEnums::SUPPLIER_DRAFT,
        ]);
        Contract::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'HD-2026-0001',
            'title' => 'Hợp đồng thử nghiệm',
            'status' => ContractEnums::CONTRACT_DRAFTING,
        ]);

        $this->actingAs($manager)
            ->deleteJson("/api/contract/suppliers/{$supplier->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('contract_suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }
}
