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
}
