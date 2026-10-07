<?php

namespace Tests\Unit\Identity;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Tests\TestCase;

class HrmEmployeeDirectoryTest extends TestCase
{
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
        $schema->create('employees', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid');
            $table->string('code')->nullable();
            $table->string('full_name')->nullable();
            $table->string('company_email')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->string('direct_manager_name')->nullable();
            $table->string('job_title_name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('companies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
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
            $table->string('path')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('positions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('title')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->unsignedTinyInteger('level')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('job_titles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('position_levels', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('employee_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('open_primary_employee_id')->nullable();
        });
    }

    public function test_load_reads_primary_assignment_and_manager_from_database(): void
    {
        $hrm = DB::connection('hrm');
        $hrm->table('companies')->insert([
            'id' => 1,
            'uuid' => 'co-1',
            'code' => 'VAS',
            'name' => 'VA Schools',
        ]);
        $hrm->table('employees')->insert([
            'id' => 7,
            'uuid' => 'emp-manager',
            'code' => 'QL001',
            'full_name' => 'Trần Quản lý',
            'status' => 'active',
        ]);
        $hrm->table('org_units')->insert([
            'id' => 3,
            'uuid' => 'ou-1',
            'company_id' => 1,
            'type' => 'department',
            'code' => 'CNTT',
            'name' => 'Phòng Công nghệ',
            'status' => 'active',
            'manager_employee_id' => 7,
        ]);
        $hrm->table('job_titles')->insert(['id' => 1, 'name' => 'Kỹ sư']);
        $hrm->table('position_levels')->insert(['id' => 1, 'level' => 2, 'name' => 'Nhân viên']);
        $hrm->table('positions')->insert([
            'id' => 1,
            'title' => 'Kỹ sư',
            'job_title_id' => 1,
            'level' => 2,
        ]);
        $hrm->table('employees')->insert([
            'id' => 9,
            'uuid' => '9968e9b8-a011-4d46-b6ad-3ff28ac584a6',
            'code' => 'NV009',
            'full_name' => 'Nguyễn Văn A',
            'company_email' => 'a.nguyen@vaschools.edu.vn',
            'phone' => '0901',
            'status' => 'active',
        ]);
        $hrm->table('employee_assignments')->insert([
            'employee_id' => 9,
            'company_id' => 1,
            'org_unit_id' => 3,
            'position_id' => 1,
            'is_primary' => 1,
            'open_primary_employee_id' => 9,
        ]);
        $hrm->table('employee_assignments')->insert([
            'employee_id' => 9,
            'company_id' => 1,
            'org_unit_id' => 3,
            'position_id' => 1,
            'is_primary' => 0,
            'effective_to' => null,
        ]);

        $loaded = (new HrmEmployeeDirectory)->load();
        $employee = collect($loaded['employees'])->firstWhere('code', 'NV009');

        $this->assertNotNull($employee);
        $this->assertSame('Nguyễn Văn A', $employee['full_name']);
        $this->assertSame('Phòng Công nghệ', $employee['org_unit_name']);
        $this->assertSame('VA Schools', $employee['company_name']);
        $this->assertSame('Kỹ sư', $employee['job_title']);
        $this->assertSame('Nhân viên', $employee['level_name']);
        $this->assertSame('Trần Quản lý', $employee['manager_name']);
        $this->assertSame('Kỹ sư', $employee['concurrent_positions'][0]['job_title_name']);
        $this->assertSame('Phòng Công nghệ', $loaded['org_units'][0]['name']);
    }
}
