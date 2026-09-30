<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Identity\App\Hrm\Jobs\ProcessEmployeeTerminatedJob;
use Modules\Identity\App\Hrm\Jobs\ProcessEmployeeUpdatedJob;
use Modules\Identity\App\Hrm\Jobs\ProcessOrgUnitChangedJob;
use Modules\Identity\App\Models\Department;
use Tests\TestCase;

class HrmWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.hrm.webhook_secret' => self::SECRET]);
    }

    private function postWebhook(string $event, string $deliveryId, array $data): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => $event, 'delivery_id' => $deliveryId, 'occurred_at' => now()->toIso8601String(), 'source' => 'va-hrm', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, self::SECRET);

        $response = $this->call('POST', '/api/hrm/webhook', server: [
            'HTTP_X-VA-HRM-Signature' => $signature,
            'HTTP_X-VA-HRM-Event' => $event,
            'HTTP_X-VA-HRM-Delivery-Id' => $deliveryId,
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);

        return $response;
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $body = json_encode(['event' => 'employee.updated', 'data' => ['employee_uuid' => 'e1']]);

        $response = $this->call('POST', '/api/hrm/webhook', server: [
            'HTTP_X-VA-HRM-Signature' => 'wrong-signature',
            'HTTP_X-VA-HRM-Event' => 'employee.updated',
            'HTTP_X-VA-HRM-Delivery-Id' => 'delivery-1',
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);

        $response->assertUnauthorized();
    }

    public function test_webhook_accepts_valid_signature_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postWebhook('employee.updated', 'delivery-2', [
            'employee_uuid' => 'emp-1',
            'changed_fields' => ['full_name'],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessEmployeeUpdatedJob::class, fn ($job) => $job->employeeUuid === 'emp-1');
    }

    public function test_webhook_duplicate_delivery_id_is_idempotent(): void
    {
        Queue::fake();

        $this->postWebhook('employee.updated', 'delivery-dup', ['employee_uuid' => 'emp-1', 'changed_fields' => []])
            ->assertOk();
        $this->postWebhook('employee.updated', 'delivery-dup', ['employee_uuid' => 'emp-1', 'changed_fields' => []])
            ->assertOk();

        Queue::assertPushed(ProcessEmployeeUpdatedJob::class, 1);
    }

    public function test_employee_updated_job_syncs_user_fields_without_touching_department_or_roles(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create([
            'hrm_employee_uuid' => 'emp-2',
            'department_id' => $department->id,
        ]);

        Http::fake([
            'https://hrm.test/api/v1/employees/emp-2' => Http::response([
                'data' => [
                    'uuid' => 'emp-2',
                    'code' => 'NV002',
                    'full_name' => $user->name,
                    'status' => 'active',
                    'company_email' => $user->email,
                    'manager_uuid' => null,
                    'manager_code' => null,
                    'manager_email' => null,
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'effective_from' => null,
                        'effective_to' => null,
                        'company' => null,
                        'org_unit' => null,
                        'position' => ['uuid' => 'pos-1', 'code' => 'DEV', 'title' => 'Lập trình viên', 'level' => 3],
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-2/manager' => Http::response(['data' => null], 200),
        ]);
        config(['services.hrm.api_base_url' => 'https://hrm.test']);

        (new ProcessEmployeeUpdatedJob('emp-2', ['full_name']))->handle(
            app(\Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface::class),
            app(\Modules\Identity\App\Hrm\Services\HrmApiClient::class),
            app(\Modules\Identity\App\Hrm\Services\HrmEmployeeSyncService::class),
        );

        $user->refresh();
        $this->assertSame('NV002', $user->employee_code);
        $this->assertSame('Lập trình viên', $user->job_title_name);
        $this->assertSame($department->id, $user->department_id);
    }

    public function test_employee_terminated_job_locks_account(): void
    {
        $user = User::factory()->create(['hrm_employee_uuid' => 'emp-3', 'status' => 'active']);

        (new ProcessEmployeeTerminatedJob('emp-3', '2026-01-15'))->handle(
            app(\Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface::class),
        );

        $user->refresh();
        $this->assertSame('inactive', $user->status);
        $this->assertNotNull($user->hrm_terminated_at);
    }

    public function test_org_unit_changed_updates_existing_department_only(): void
    {
        $department = Department::factory()->create(['hrm_org_unit_uuid' => 'ou-1', 'name' => 'Tên cũ']);

        Http::fake([
            'https://hrm.test/api/v1/org-units/ou-1' => Http::response([
                'data' => ['uuid' => 'ou-1', 'name' => 'Tên mới', 'code' => 'OU1', 'company' => null],
            ], 200),
        ]);
        config(['services.hrm.api_base_url' => 'https://hrm.test']);

        (new ProcessOrgUnitChangedJob('ou-1', 'renamed'))->handle(
            app(\Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface::class),
            app(\Modules\Identity\App\Hrm\Services\HrmApiClient::class),
        );

        $department->refresh();
        $this->assertSame('Tên mới', $department->name);

        // change_type=created và không có Department khớp -> không tạo mới.
        $countBefore = Department::query()->count();
        (new ProcessOrgUnitChangedJob('ou-not-mapped', 'created'))->handle(
            app(\Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface::class),
            app(\Modules\Identity\App\Hrm\Services\HrmApiClient::class),
        );
        $this->assertSame($countBefore, Department::query()->count());
    }
}
