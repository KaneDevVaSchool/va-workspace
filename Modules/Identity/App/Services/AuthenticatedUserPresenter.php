<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuVisibilityRepositoryInterface;

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
        private readonly GlobalMenuVisibilityRepositoryInterface $globalMenus,
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
            // Permission keys hiệu lực (config + DB grant, có tính view-as).
            // ['*'] nếu là super_admin thực sự; catalog keys đang được cấp nếu không.
            'granted_permissions' => $this->permissions->resolveGrantedKeys($user),
            // Menu sidebar bị phòng ban của user tự tắt / đổi tên / sắp xếp (xem AppSidebar.vue).
            'hidden_menu_keys' => $user->department
                ? $this->sidebarConfigs->hiddenKeysForDepartment($user->department->id)
                : [],
            'menu_labels' => $user->department
                ? $this->sidebarConfigs->customLabelsForDepartment($user->department->id)
                : (object) [],
            'menu_order' => $user->department
                ? $this->sidebarConfigs->sortOrdersForDepartment($user->department->id)
                : (object) [],
            'menu_item_sections' => $user->department
                ? $this->sidebarConfigs->itemSectionsForDepartment($user->department->id)
                : (object) [],
            'menu_section_labels' => $user->department
                ? $this->sidebarConfigs->sectionLabelsForDepartment($user->department->id)
                : (object) [],
            // Menu bị ẩn TOÀN HỆ THỐNG (superadmin cấu hình) — LUÔN trả,
            // không phụ thuộc department, kể cả với chính super_admin.
            // super_admin không bị ảnh hưởng khi hiển thị sidebar
            // (AppSidebar.vue bỏ qua khi showSuperAdminNav=true) — dữ liệu
            // thật vẫn cần trả để trang quản lý switch tự hiển thị đúng.
            'globally_hidden_menu_keys' => $this->globalMenus->hiddenKeys(),
        ];
    }
}
