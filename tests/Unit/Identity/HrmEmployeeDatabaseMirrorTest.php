<?php

namespace Tests\Unit\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
