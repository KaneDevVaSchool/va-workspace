<?php

namespace Tests\Unit\Identity;

use Illuminate\Support\Facades\Http;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;
use Tests\TestCase;

class HrmApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-token',
        ]);
    }

    public function test_get_employee_maps_dto_fields(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees/emp-1' => Http::response([
                'data' => [
                    'uuid' => 'emp-1',
                    'code' => 'NV001',
                    'full_name' => 'Nguyễn Văn A',
                    'status' => 'active',
                    'company_email' => 'a@vaschools.edu.vn',
                    'manager_uuid' => 'mgr-1',
                    'manager_code' => 'NV000',
                    'manager_email' => 'boss@vaschools.edu.vn',
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'effective_from' => '2024-01-01',
                        'effective_to' => null,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-1', 'name' => 'Phòng CNTT', 'path' => '/1/2'],
                        'position' => ['uuid' => 'pos-1', 'code' => 'DEV', 'title' => 'Lập trình viên', 'level' => 3],
                    ],
                    'concurrent_assignments' => [[
                        'is_primary' => false,
                        'is_current' => true,
                        'effective_from' => '2024-06-01',
                        'effective_to' => null,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-2', 'name' => 'Ban Dự án', 'path' => '/1/3'],
                        'position' => ['uuid' => 'pos-2', 'code' => 'PM', 'title' => 'Điều phối dự án', 'level' => 4],
                    ]],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-1/manager' => Http::response([
                'data' => ['uuid' => 'mgr-1', 'code' => 'NV000', 'full_name' => 'Trần Thị B', 'company_email' => 'boss@vaschools.edu.vn', 'status' => 'active'],
            ], 200),
        ]);

        $employee = (new HrmApiClient())->getEmployee('emp-1');

        $this->assertSame('emp-1', $employee->uuid);
        $this->assertSame('NV001', $employee->code);
        $this->assertSame('mgr-1', $employee->managerEmployeeUuid);
        $this->assertSame('Trần Thị B', $employee->managerDisplayName);
        $this->assertNotNull($employee->primaryAssignment);
        $this->assertSame('Lập trình viên', $employee->primaryAssignment->jobTitleName);
        $this->assertCount(1, $employee->concurrentAssignments);
        $this->assertSame('Điều phối dự án', $employee->concurrentAssignments[0]->jobTitleName);
    }

    public function test_get_employee_throws_on_server_error(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees/emp-1' => Http::response([], 500),
        ]);

        $this->expectException(HrmApiUnavailable::class);
        (new HrmApiClient())->getEmployee('emp-1');
    }

    public function test_get_employee_returns_null_on_404(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees/emp-missing' => Http::response([], 404),
        ]);

        $this->assertNull((new HrmApiClient())->getEmployee('emp-missing'));
    }

    public function test_verify_sso_token_returns_claims(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/auth/verify-token' => Http::response([
                'data' => ['sub' => 'hrm-1', 'aud' => 'va-workspace', 'email' => 'a@vaschools.edu.vn'],
            ], 200),
        ]);

        $claims = (new HrmApiClient())->verifySsoToken('jwt-example');

        $this->assertSame('hrm-1', $claims['sub']);
    }
}
