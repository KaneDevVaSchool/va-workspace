<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuSectionConfigRepositoryInterface;
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
        private readonly DefaultMemberRoleBootstrap $defaultMemberRole,
        private readonly PermissionService $permissions,
        private readonly DepartmentSidebarConfigRepositoryInterface $sidebarConfigs,
        private readonly GlobalMenuVisibilityRepositoryInterface $globalMenus,
        private readonly GlobalMenuSectionConfigRepositoryInterface $globalMenuSections,
        private readonly HrmDepartmentSyncService $hrmDepartments,
    ) {}

    public function forUser(User $user): array
    {
        $this->superAdminBootstrap->ensureRolesForUser($user);
        $this->defaultMemberRole->ensureForUser($user);
        $user->unsetRelation('roles');
        $user->load(['department', 'roles', 'company', 'concurrentPositions']);

        // Super admin (không đang xem thử) không mang ngữ cảnh phòng ban.
        // Xem thử vai trò khác thì phòng ban lấy từ HRM, không từ department Workspace.
        $department = ($user->isSuperAdmin() && ! $this->viewAs->isImpersonating())
            ? null
            : $this->hrmDepartments->hrmDepartmentFor($user);
        $menuDepartment = $user->department;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'department' => $department,
            // Đồng bộ từ VA-HRM (Modules/Identity/App/Hrm) — chỉ hiển thị.
            'employee_code' => $user->employee_code,
            'job_title_name' => $user->job_title_name,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'code' => $user->company->code,
                'name' => $user->company->name,
            ] : null,
            'manager_display_name' => $user->manager_display_name,
            'concurrent_positions' => $user->concurrentPositions->map(fn ($position) => [
                'job_title_name' => $position->job_title_name,
                'company_name' => $position->company_name,
                'org_unit_name' => $position->org_unit_name,
            ])->values()->all(),
            'hrm_terminated_at' => $user->hrm_terminated_at?->toIso8601String(),
            'roles' => $user->roles->pluck('code')->values()->all(),
            'active_role' => $this->viewAs->displayActiveRole($user),
            'is_impersonating' => $this->viewAs->isImpersonating(),
            'can_view_as' => $user->isSuperAdmin(),
            // Permission keys hiệu lực (config + DB grant, có tính view-as).
            // ['*'] nếu là super_admin thực sự; catalog keys đang được cấp nếu không.
            'granted_permissions' => $this->permissions->resolveGrantedKeys($user),
            // Menu sidebar bị phòng ban của user tự tắt / đổi tên / sắp xếp (xem AppSidebar.vue).
            'hidden_menu_keys' => $menuDepartment
                ? $this->sidebarConfigs->hiddenKeysForDepartment($menuDepartment->id)
                : [],
            'menu_labels' => $menuDepartment
                ? $this->sidebarConfigs->customLabelsForDepartment($menuDepartment->id)
                : (object) [],
            'menu_order' => $menuDepartment
                ? $this->sidebarConfigs->sortOrdersForDepartment($menuDepartment->id)
                : (object) [],
            'menu_item_sections' => $menuDepartment
                ? $this->sidebarConfigs->itemSectionsForDepartment($menuDepartment->id)
                : (object) [],
            'menu_section_labels' => $menuDepartment
                ? $this->sidebarConfigs->sectionLabelsForDepartment($menuDepartment->id)
                : (object) [],
            // Menu bị ẩn TOÀN HỆ THỐNG (superadmin cấu hình) — LUÔN trả,
            // không phụ thuộc department. Áp dụng cho MỌI tài khoản kể cả
            // super_admin, không có ngoại lệ (xem AppSidebar.vue::itemPasses) —
            // trang cấu hình chính nó luôn được bảo vệ (PROTECTED_MENU_KEYS)
            // nên super_admin luôn còn đường vào lại để bật menu đã ẩn.
            'globally_hidden_menu_keys' => $this->globalMenus->hiddenKeys(),
            // Tên, thứ tự và nhóm tuỳ chỉnh toàn hệ thống (superadmin cấu hình).
            // Áp dụng cho MỌI tài khoản; department override thắng nếu có.
            'global_menu_labels' => (object) $this->globalMenus->customLabels(),
            'global_menu_order' => (object) $this->globalMenus->sortOrders(),
            'global_menu_item_sections' => (object) $this->globalMenus->itemSections(),
            'global_menu_section_labels' => (object) $this->globalMenuSections->sectionLabels(),
        ];
    }
}
