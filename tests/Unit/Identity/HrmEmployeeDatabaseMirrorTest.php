<?php

namespace Tests\Unit\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Tests\TestCase;

class HrmEmployeeDatabaseMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_mirror_one_creates_user_from_hrm_employee_payload(): void
    {
        config(['database.connections.hrm.database' => 'hrm_test']);

        $this->mock(HrmEmployeeDirectory::class, function ($mock): void {
            $mock->shouldReceive('load')->never();
        });

        $mirror = app(HrmEmployeeDatabaseMirrorService::class);

        $user = $mirror->mirrorOne([
            'uuid' => 'emp-mirror-1',
            'full_name' => 'Nguyễn Văn A',
            'company_email' => 'a@vaschools.edu.vn',
            'code' => 'NV001',
            'job_title' => 'Giáo viên',
            'level_name' => 'Cấp 1',
            'manager_name' => 'Trưởng phòng',
            'status' => 'active',
            'company_uuid' => 'co-1',
            'company_code' => 'VA',
            'company_name' => 'VA Schools',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('emp-mirror-1', $user->hrm_employee_uuid);
        $this->assertSame('a@vaschools.edu.vn', $user->email);
        $this->assertSame('NV001', $user->employee_code);
        $this->assertNotNull($user->hrm_synced_at);
    }

    public function test_mirror_one_keeps_uuid_when_company_email_is_shared(): void
    {
        config([
            'database.connections.hrm' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('hrm');

        Schema::connection('hrm')->create('employees', function ($table): void {
            $table->id();
            $table->string('uuid');
            $table->string('company_email')->nullable();
        });
        DB::connection('hrm')->table('employees')->insert([
            ['uuid' => 'uuid-kept', 'company_email' => 'dup@vaschools.edu.vn'],
            ['uuid' => 'uuid-other', 'company_email' => 'dup@vaschools.edu.vn'],
        ]);

        $existing = User::factory()->create([
            'email' => 'dup@vaschools.edu.vn',
            'hrm_employee_uuid' => 'uuid-kept',
        ]);

        $this->mock(HrmEmployeeDirectory::class, function ($mock): void {
            $mock->shouldReceive('load')->never();
        });

        $returned = app(HrmEmployeeDatabaseMirrorService::class)->mirrorOne([
            'uuid' => 'uuid-other',
            'full_name' => 'Người trùng email',
            'company_email' => 'dup@vaschools.edu.vn',
            'code' => 'NV-DUP',
            'status' => 'active',
        ]);

        $this->assertSame($existing->id, $returned?->id);
        $this->assertSame('uuid-kept', $existing->fresh()->hrm_employee_uuid);
        $this->assertNotSame('NV-DUP', $existing->fresh()->employee_code);
    }
}
