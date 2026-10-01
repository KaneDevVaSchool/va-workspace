<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Exceptions\AccountNotUsable;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\DefaultMemberRoleBootstrap;
use Modules\Identity\App\Services\SuperAdminBootstrap;

/**
 * Xử lý callback SSO VA-HRM: map claims JWT đã xác thực -> User workspace
 * (tìm/tạo), fetch hồ sơ nhân sự lần đầu nếu cần. Theo pattern
 * GoogleAuthenticator hiện có (Service -> Repository interface, không
 * Eloquent trực tiếp).
 */
class HrmSsoService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly HrmApiClient $hrmApi,
        private readonly HrmEmployeeSyncService $sync,
        private readonly SuperAdminBootstrap $superAdminBootstrap,
        private readonly DefaultMemberRoleBootstrap $defaultMemberRole,
    ) {}

    /**
     * @param array<string, mixed> $claims JWT claims đã xác thực (HrmJwtVerifier::verify())
     * @throws AccountNotUsable
     */
    public function authenticate(array $claims): User
    {
        $hrmUserUuid = (string) $claims['sub'];
        $employeeUuid = $claims['employee_uuid'] ?? null;
        $email = strtolower((string) $claims['email']);
        $name = (string) ($claims['name'] ?? $email);

        $user = DB::transaction(function () use ($hrmUserUuid, $employeeUuid, $email, $name, $claims): User {
            $user = $this->users->findByHrmUserUuid($hrmUserUuid)
                ?? ($employeeUuid !== null ? $this->users->findByHrmEmployeeUuid($employeeUuid) : null)
                ?? $this->users->findByEmail($email);

            $isNew = $user === null;

            if ($user === null) {
                $user = $this->users->create([
                    'name' => $name,
                    'email' => $email,
                    'hrm_user_uuid' => $hrmUserUuid,
                    'hrm_employee_uuid' => $employeeUuid,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            } else {
                $user = $this->users->update($user, array_filter([
                    'name' => $name,
                    'email' => $email,
                    'hrm_user_uuid' => $hrmUserUuid,
                    'hrm_employee_uuid' => $employeeUuid ?? $user->hrm_employee_uuid,
                ], fn ($value) => $value !== null));
            }

            if ($isNew && $employeeUuid !== null) {
                $this->fetchAndApplyEmployeeProfile($user, $employeeUuid);
            }

            $this->assertUsable($user, $email);
            $this->superAdminBootstrap->ensureRolesForUser($user);
            $this->defaultMemberRole->ensureForUser($user);

            Log::info('hrm_sso.login', [
                'user_id' => $user->id,
                'hrm_roles' => $claims['roles'] ?? [],
            ]);

            return $user->fresh(['roles']);
        });

        return $user;
    }

    /**
     * Nếu HRM lỗi ngay lúc này (chết ngay sau khi phát JWT) — không chặn
     * login, chỉ log warning; hrm_synced_at giữ null để webhook lần sau bù.
     */
    private function fetchAndApplyEmployeeProfile(User $user, string $employeeUuid): void
    {
        try {
            $employee = $this->hrmApi->getEmployee($employeeUuid);
        } catch (HrmApiUnavailable $e) {
            Log::warning('hrm_sso.fetch_employee_failed', [
                'user_id' => $user->id,
                'employee_uuid' => $employeeUuid,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($employee !== null) {
            $this->sync->applyToUser($user, $employee);
        }
    }

    /** @throws AccountNotUsable */
    private function assertUsable(User $user, string $email): void
    {
        if ($user->status !== 'active') {
            throw new AccountNotUsable($email, $user->status);
        }
    }
}
