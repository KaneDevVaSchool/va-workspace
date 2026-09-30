<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Kéo TOÀN BỘ nhân viên VA-HRM (GET /api/v1/employees) và tạo sẵn User local
 * cho người CHƯA từng đăng nhập workspace — để superadmin thấy đủ nhân sự ở
 * trang "Nhân sự workspace" (/superadmin/workspace-config/unassigned) mà
 * không phải chờ từng người tự SSO lần đầu. Người đã có User (khớp
 * hrm_employee_uuid) chỉ được cập nhật hiển thị qua HrmEmployeeSyncService,
 * giống hệt luồng SSO/webhook — không tạo trùng, không đụng roles.
 */
class HrmEmployeeBulkSyncService
{
    /** Trạng thái HRM coi là còn hoạt động — set 'inactive' cho các case còn lại (terminated, suspended…). */
    private const ACTIVE_HRM_STATUSES = ['active', 'processing', 'pending_confirmation', 'on_leave'];

    public function __construct(
        private readonly HrmApiClient $hrmApi,
        private readonly HrmEmployeeSyncService $sync,
        private readonly UserRepositoryInterface $users,
    ) {}

    public static function isConfigured(): bool
    {
        return HrmDepartmentSyncService::isConfigured();
    }

    /**
     * Không làm gì khi chưa cấu hình HRM; lỗi API được log, không chặn
     * trang (fallback danh sách User local hiện có).
     */
    public function syncEmployeesFromHrm(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        try {
            $employees = $this->hrmApi->listAllEmployees();
        } catch (HrmApiUnavailable $e) {
            Log::warning('hrm.employee_bulk_sync.failed', ['message' => $e->getMessage()]);

            return;
        }

        foreach ($employees as $payload) {
            if (! is_array($payload) || ! isset($payload['uuid'])) {
                continue;
            }

            $this->syncOne($payload);
        }
    }

    /** @param array<string, mixed> $payload EmployeeResource (item trong list, không có assignments) */
    private function syncOne(array $payload): void
    {
        $employeeUuid = (string) $payload['uuid'];
        $user = $this->users->findByHrmEmployeeUuid($employeeUuid);

        if ($user === null) {
            $user = $this->createFromListPayload($payload);
            if ($user === null) {
                return;
            }
        }

        // Lấy full payload (kèm assignments) để áp company/department/manager
        // — EmployeeResource trong list index không load assignments.
        try {
            $full = $this->hrmApi->getEmployee($employeeUuid);
        } catch (HrmApiUnavailable $e) {
            Log::warning('hrm.employee_bulk_sync.fetch_detail_failed', [
                'employee_uuid' => $employeeUuid,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($full !== null) {
            $this->sync->applyToUser($user, $full);
        }

        $this->applyStatus($user, (string) ($payload['status'] ?? 'active'));
    }

    /** @param array<string, mixed> $payload */
    private function createFromListPayload(array $payload): ?User
    {
        $email = $this->resolveEmail($payload);
        if ($email === null) {
            Log::warning('hrm.employee_bulk_sync.no_email', ['employee_uuid' => $payload['uuid']]);

            return null;
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            // Email đã có User (khả năng đã SSO/Google trước khi có employee_uuid) — chỉ gắn liên kết, không tạo trùng.
            return $this->users->update($existing, ['hrm_employee_uuid' => (string) $payload['uuid']]);
        }

        return $this->users->create([
            'name' => (string) ($payload['full_name'] ?? $email),
            'email' => $email,
            'hrm_employee_uuid' => (string) $payload['uuid'],
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function resolveEmail(array $payload): ?string
    {
        $email = $payload['company_email'] ?? $payload['personal_email'] ?? null;

        return is_string($email) && $email !== '' ? strtolower($email) : null;
    }

    private function applyStatus(User $user, string $hrmStatus): void
    {
        $nextStatus = in_array($hrmStatus, self::ACTIVE_HRM_STATUSES, true) ? 'active' : 'inactive';

        if ($user->status !== $nextStatus) {
            $this->users->update($user, ['status' => $nextStatus]);
        }
    }
}
