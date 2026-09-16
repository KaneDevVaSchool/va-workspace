<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigGlobalMenuController;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigMemberController;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigOverviewController;
use Modules\WorkspaceConfig\App\Http\Requests\ReorderGlobalMenuLayoutRequest;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateGlobalMenuSectionRequest;

/*
|--------------------------------------------------------------------------
| WorkspaceConfig Module — Superadmin Routes
|--------------------------------------------------------------------------
| Xem tổng hợp workspace của TẤT CẢ phòng ban (chỉ super_admin, key
| workspace_config.view_all, reserved) — 1 bảng liệt kê phòng ban + số
| liệu tóm tắt, bấm vào 1 dòng để xem chi tiết phòng ban đó. Phần lớn chỉ
| xem, riêng gán vai trò (roles.assign) super_admin được làm thay
| department_director cho bất kỳ phòng ban nào.
|
| Prefix thật: /api/workspace-config/* (ServiceProvider), tách khỏi trang
| Vue /superadmin/workspace-config và /superadmin/workspace-config/departments/:id.
*/

Route::middleware(['auth', 'permission:workspace_config.view_all'])
    ->prefix('workspace-config')->name('workspace-config.')
    ->group(function () {
        Route::get('/overview', [WorkspaceConfigOverviewController::class, 'index'])->name('overview');
        Route::get('/departments/{department}', [WorkspaceConfigOverviewController::class, 'showDepartment'])->name('department-detail');
        // Ngoại lệ duy nhất được ghi ở khu vực này: department_id là điều
        // kiện tiên quyết để department_director thấy được trang cấu hình
        // phòng ban của chính họ (xem WorkspaceConfigMemberController::departmentIdOrFail()).
        Route::get('/members/unassigned', [WorkspaceConfigOverviewController::class, 'unassignedMembers'])->name('members.unassigned');
        Route::put('/members/{user}/department', [WorkspaceConfigOverviewController::class, 'assignDepartment'])->name('members.assign-department');
        // super_admin gán vai trò thay department_director cho 1 phòng ban
        // bất kỳ (department lấy từ route param, không phải phòng ban của
        // chính super_admin) — dùng ở tab "Thành viên" trong hub chi tiết
        // phòng ban, xem WorkspaceConfigMemberController::assignRoleForDepartment().
        Route::post('/departments/{department}/members/roles', [WorkspaceConfigMemberController::class, 'assignRoleForDepartment'])
            ->name('departments.members.roles.assign');
    });

/*
|--------------------------------------------------------------------------
| Ẩn/hiện menu sidebar Ở MỨC TOÀN HỆ THỐNG (super_admin, key
| workspace_config.manage_global_menu, reserved) — áp dụng cho mọi tài
| khoản không phải super_admin, thắng tuyệt đối per-department override.
*/
Route::middleware(['auth', 'permission:workspace_config.manage_global_menu'])
    ->prefix('workspace-config/global-menu')->name('workspace-config.global-menu.')
    ->group(function () {
        Route::get('/', [WorkspaceConfigGlobalMenuController::class, 'index'])->name('index');
        Route::put('/', [WorkspaceConfigGlobalMenuController::class, 'update'])->name('update');
        Route::put('/section', [WorkspaceConfigGlobalMenuController::class, 'updateSection'])->name('update-section');
        Route::put('/layout', [WorkspaceConfigGlobalMenuController::class, 'reorderLayout'])->name('reorder-layout');
    });
