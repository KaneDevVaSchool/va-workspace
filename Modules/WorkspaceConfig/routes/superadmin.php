<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigGlobalMenuController;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigOverviewController;
use Modules\WorkspaceConfig\App\Http\Requests\ReorderGlobalMenuLayoutRequest;
use Modules\WorkspaceConfig\App\Http\Requests\UpdateGlobalMenuSectionRequest;

/*
|--------------------------------------------------------------------------
| WorkspaceConfig Module — Superadmin Routes
|--------------------------------------------------------------------------
| Xem tổng hợp workspace của TẤT CẢ phòng ban (chỉ super_admin, key
| workspace_config.view_all, reserved) — 1 bảng liệt kê phòng ban + số
| liệu tóm tắt, bấm vào 1 dòng để xem chi tiết phòng ban đó. Chỉ xem,
| không sửa thay department_director.
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
