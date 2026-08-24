<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigOverviewController;

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
    });
