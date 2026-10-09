<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeLeaveWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);
    }

    public function test_leave_workflow_uses_direct_manager_when_available(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees/emp-self/manager' => Http::response([
                'data' => [
                    'uuid' => 'mgr-1',
                    'full_name' => 'Quản Lý Trực Tiếp',
                    'status' => 'active',
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/mgr-1' => Http::response([
                'data' => [
                    'uuid' => 'mgr-1',
                    'code' => 'NV002',
                    'full_name' => 'Quản Lý Trực Tiếp',
                    'status' => 'active',
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-1', 'name' => 'Phần Mềm', 'path' => '/1'],
                        'position' => ['title' => 'Trưởng nhóm', 'level' => 4],
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-self' => Http::response([
                'data' => [
                    'uuid' => 'emp-self',
                    'code' => 'NV001',
                    'full_name' => 'Nhân Viên',
                    'status' => 'active',
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-1', 'name' => 'Phần Mềm', 'path' => '/1'],
                        'position' => ['title' => 'Dev', 'level' => 3],
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'hrm_employee_uuid' => 'emp-self',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/attendance/leave-workflow')
            ->assertOk()
            ->assertJsonPath('data.approver.name', 'Quản Lý Trực Tiếp')
            ->assertJsonPath('data.approver.source', 'direct_manager')
            ->assertJsonPath('data.approver_group_label', 'Người duyệt nhóm Phần Mềm');
    }

    public function test_leave_workflow_requires_hrm_employee_link(): void
    {
        $user = User::factory()->create([
            'hrm_employee_uuid' => null,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/attendance/leave-workflow')
            ->assertOk()
            ->assertJsonPath('data.approver', null)
            ->assertJsonFragment(['message' => 'Tài khoản chưa liên kết nhân sự HRM.']);
    }

    public function test_leave_workflow_includes_hr_owner_from_employee_payload(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees/emp-self/manager' => Http::response(['data' => null], 200),
            'https://hrm.test/api/v1/employees/emp-self*' => Http::response([
                'data' => [
                    'uuid' => 'emp-self',
                    'code' => 'NV001',
                    'full_name' => 'Nhân Viên',
                    'status' => 'active',
                    'direct_manager_name' => 'Quản Lý (NV002)',
                    'hr_owner' => [
                        'uuid' => 'hr-1',
                        'code' => 'VA011408',
                        'full_name' => 'Cao Thị Linh Tuyền',
                        'job_title_name' => 'Nhân sự',
                    ],
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-1', 'name' => 'Phần Mềm', 'path' => '/1'],
                        'position' => ['title' => 'Dev', 'level' => 3],
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/hr-1' => Http::response([
                'data' => [
                    'uuid' => 'hr-1',
                    'code' => 'VA011408',
                    'full_name' => 'Cao Thị Linh Tuyền',
                    'status' => 'active',
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-hr', 'name' => 'HCNS', 'path' => '/2'],
                        'position' => ['title' => 'Nhân sự', 'level' => 3],
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees*' => Http::response(['data' => []], 200),
        ]);

        $user = User::factory()->create([
            'hrm_employee_uuid' => 'emp-self',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson('/api/attendance/leave-workflow')
            ->assertOk()
            ->assertJsonPath('data.hr_responsible.full_name', 'Cao Thị Linh Tuyền')
            ->assertJsonPath('data.hr_responsible.code', 'VA011408');
    }
}
