<?php

namespace Modules\DonTu\App\Support;

use App\Models\User;
use Modules\Identity\App\Services\PermissionService;

final class DonTuConfigAccess
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function canReadConfig(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->permissions->allows($user, 'don_tu.manage_system')
            || $this->permissions->allows($user, 'don_tu.manage_department')
            || $this->permissions->allows($user, 'don_tu.*');
    }

    public function canWriteConfig(?User $user, ?int $departmentId = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->permissions->allows($user, 'don_tu.*')
            || $this->permissions->allows($user, 'don_tu.manage_system')) {
            return true;
        }

        if ($departmentId === null) {
            return $this->permissions->allows($user, 'don_tu.manage_department');
        }

        return $this->permissions->allows(
            $user,
            'don_tu.manage_department',
            'department',
            $departmentId,
        );
    }

    /** Mẫu/quy trình hệ thống (department_id null) — chỉ manage_system hoặc *. */
    public function canWriteSystemScope(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->permissions->allows($user, 'don_tu.*')
            || $this->permissions->allows($user, 'don_tu.manage_system');
    }
}
