<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\App\Models\Department;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class HrmWorkspaceDepartmentAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.hrm' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'va_hrm_',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('hrm');

        $schema = Schema::connection('hrm');
        $schema->create('companies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
        });
        $schema->create('employees', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid');
            $table->string('company_email')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('org_units', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('type')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('employee_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $table->unsignedBigInteger('open_primary_employee_id')->nullable();
        });

        $hrm = DB::connection('hrm');
        $hrm->table('companies')->insert([
            'id' => 1,
            'uuid' => 'co-1',
            'code' => 'VAS',
            'name' => 'VA Schools',
        ]);
        $hrm->table('org_units')->insert([
            'id' => 3,
            'uuid' => 'ou-dept',
            'company_id' => 1,
            'type' => 'department',
            'code' => 'PMKT',
            'name' => 'Phòng Marketing',
            'status' => 'active',
        ]);
        $hrm->table('org_units')->insert([
            'id' => 4,
            'uuid' => 'ou-unit',
            'parent_id' => 3,
            'company_id' => 1,
            'type' => 'unit',
            'code' => 'TV',
            'name' => 'Tư vấn',
            'status' => 'active',
        ]);
        $hrm->table('employees')->insert([
            'id' => 9,
            'uuid' => 'emp-1',
            'company_email' => 'tu.van@vaschools.edu.vn',
        ]);
        $hrm->table('employee_assignments')->insert([
            'employee_id' => 9,
            'org_unit_id' => 4,
            'open_primary_employee_id' => 9,
        ]);
    }

    public function test_api_me_assigns_hrm_department_not_the_unit(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create([
            'status' => 'active',
            'email' => 'tu.van@vaschools.edu.vn',
            'hrm_employee_uuid' => 'emp-1',
            'department_id' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('department.name', 'Phòng Marketing');

        $department = Department::query()->where('hrm_org_unit_uuid', 'ou-dept')->first();
        $this->assertNotNull($department);
        $this->assertSame($department->id, $user->fresh()->department_id);
        $this->assertNull(Department::query()->where('hrm_org_unit_uuid', 'ou-unit')->first());
    }

    public function test_api_me_does_not_replace_a_department_already_assigned(): void
    {
        $this->seed(RoleSeeder::class);

        $manual = Department::query()->create([
            'code' => 'MANUAL',
            'name' => 'Phòng đã gán tay',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'status' => 'active',
            'email' => 'tu.van@vaschools.edu.vn',
            'hrm_employee_uuid' => 'emp-1',
            'department_id' => $manual->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('department.name', 'Phòng đã gán tay');

        $this->assertSame($manual->id, $user->fresh()->department_id);
    }
}
