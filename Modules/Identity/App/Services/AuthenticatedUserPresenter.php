<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;

/**
 * JSON payload GET /api/me — dùng chung cho view-as để client cập nhật store
 * ngay từ response POST/DELETE (không phụ thuộc GET tiếp theo).
 */
class AuthenticatedUserPresenter
{
    public function __construct(
        private readonly ViewAsService $viewAs,
        private readonly SuperAdminBootstrap $superAdminBootstrap,
        private readonly PermissionService $permissions,
        private readonly DepartmentSidebarConfigRepositoryInterface $sidebarConfigs,
    ) {}

    public function forUser(User $user): array
    {
        $this->superAdminBootstrap->ensureRolesForUser($user);
        $user->unsetRelation('roles');
        $user->load(['department', 'roles']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'code' => $user->department->code,
                'name' => $user->department->name,
            ] : null,
            'roles' => $user->roles->pluck('code')->values()->all(),
            'active_role' => $this->viewAs->displayActiveRole($user),
            'is_impersonating' => $this->viewAs->isImpersonating(),
            'can_view_as' => $user->isSuperAdmin(),
            // Permission keys hiệu lực (có tính view-as) — frontend cache trong Pinia store.
            // ['*'] nếu là super_admin thực sự; danh sách keys cụ thể nếu đang view-as.
            'granted_permissions' => $this->permissions->resolveGrantedKeys($user),
            // Menu sidebar bị phòng ban của user tự tắt (xem AppSidebar.vue).
            'hidden_menu_keys' => $user->department
                ? $this->sidebarConfigs->hiddenKeysForDepartment($user->department->id)
                : [],
        ];
    }
}
