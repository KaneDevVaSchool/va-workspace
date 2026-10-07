<?php

namespace Modules\Identity\App\Hrm\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Identity\App\Models\Department;

/**
 * Ràng buộc nhân sự / phòng ban workspace với VA-HRM khi đã cấu hình DB HRM.
 * Dev/local không cấu HRM → không áp dụng (giữ hành vi cũ).
 */
class HrmWorkspaceConstraint
{
    public function isEnforced(): bool
    {
        return HrmEmployeeDirectory::isConfigured();
    }

    /** @param  Builder<User>  $query */
    public function restrictToHrmLinkedUsers(Builder $query): void
    {
        if (! $this->isEnforced()) {
            return;
        }

        $query->whereNotNull('hrm_employee_uuid');
    }

    public function userIsHrmLinked(?User $user): bool
    {
        if (! $this->isEnforced()) {
            return true;
        }

        return $user !== null && filled($user->hrm_employee_uuid);
    }

    /**
     * @param  list<int>  $ids
     */
    public function validateUserIds(array $ids): ?string
    {
        if (! $this->isEnforced()) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return null;
        }

        $invalid = User::query()
            ->whereIn('id', $ids)
            ->where(function (Builder $query): void {
                $query->whereNull('hrm_employee_uuid')->orWhere('hrm_employee_uuid', '');
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['name', 'email']);

        if ($invalid->isEmpty()) {
            return null;
        }

        $labels = $invalid
            ->map(fn (User $user) => filled($user->name) ? $user->name : ($user->email ?? ''))
            ->filter()
            ->implode(', ');

        return 'Chỉ chọn nhân sự đã liên kết với VA-HRM.'.($labels !== '' ? " Không hợp lệ: {$labels}." : '');
    }

    public function validateOptionalUserId(?int $userId, string $fieldLabel): ?string
    {
        if ($userId === null || $userId === 0) {
            return null;
        }

        $message = $this->validateUserIds([$userId]);

        return $message === null ? null : str_replace('Chỉ chọn nhân sự', "{$fieldLabel}: chỉ chọn nhân sự", $message);
    }

    /**
     * @param  list<int>  $ids
     */
    public function validateDepartmentIds(array $ids): ?string
    {
        if (! $this->isEnforced()) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return null;
        }

        $invalid = Department::query()
            ->whereIn('id', $ids)
            ->where(function (Builder $query): void {
                $query->whereNull('hrm_org_unit_uuid')
                    ->orWhere('hrm_org_unit_uuid', '')
                    ->orWhere('is_active', false);
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['name']);

        if ($invalid->isEmpty()) {
            return null;
        }

        $labels = $invalid->pluck('name')->filter()->implode(', ');

        return 'Chỉ chọn phòng ban đồng bộ từ VA-HRM.'.($labels !== '' ? " Không hợp lệ: {$labels}." : '');
    }

    public function validateOptionalDepartmentId(?int $departmentId, string $fieldLabel): ?string
    {
        if ($departmentId === null || $departmentId === 0) {
            return null;
        }

        $message = $this->validateDepartmentIds([$departmentId]);

        return $message === null ? null : str_replace('Chỉ chọn phòng ban', "{$fieldLabel}: chỉ chọn phòng ban", $message);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $watcherIds
     * @param  list<int>|null  $collaboratorIds
     */
    public function validateTaskPeople(array $data, ?array $watcherIds = null, ?array $collaboratorIds = null): ?string
    {
        $ids = [];
        if (! empty($data['assignee_id'])) {
            $ids[] = (int) $data['assignee_id'];
        }
        if (! empty($data['manager_id'])) {
            $ids[] = (int) $data['manager_id'];
        }
        if ($watcherIds !== null) {
            $ids = array_merge($ids, $watcherIds);
        }
        if ($collaboratorIds !== null) {
            $ids = array_merge($ids, $collaboratorIds);
        }

        return $this->validateUserIds($ids);
    }
}
