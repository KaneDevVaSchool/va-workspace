<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigMemberController;
use Modules\WorkspaceConfig\App\Http\Controllers\WorkspaceConfigSidebarController;

/*
|--------------------------------------------------------------------------
| WorkspaceConfig Module — Manager Routes
|--------------------------------------------------------------------------
| Hub cấu hình phòng ban của department_director/deputy_department_director:
| xem thành viên, tạo nhóm, bật/tắt menu sidebar — CHỈ áp dụng phạm vi phòng
| ban của chính user đang đăng nhập (department_id lấy từ $request->user(),
| không nhận từ query/body — khác TeamController vì ở đây không có khái niệm
| "quản lý nhiều phòng ban" cho 1 user).
|
| Prefix thật: /api/workspace-config/* (ServiceProvider), để F5 trang Vue
| /manager/workspace-config/members không bị Laravel trả JSON.
*/

Route::middleware('auth')
    ->prefix('workspace-config/members')->name('workspace-config.members.')
    ->group(function () {
        Route::get('/', [WorkspaceConfigMemberController::class, 'index'])
            ->middleware('permission:workspace_config.view_department')
            ->name('index');
        Route::post('/teams', [WorkspaceConfigMemberController::class, 'storeTeam'])
            ->middleware('permission:team.manage')
            ->name('teams.store');
        Route::put('/teams/{team}', [WorkspaceConfigMemberController::class, 'updateTeam'])
            ->middleware('permission:team.manage')
            ->name('teams.update');
        Route::put('/{user}/team', [WorkspaceConfigMemberController::class, 'assignMemberTeam'])
            ->middleware('permission:team.manage')
            ->name('members.team.assign');
        Route::post('/roles', [WorkspaceConfigMemberController::class, 'assignRole'])
            ->middleware('permission:workspace_config.assign_role_department')
            ->name('roles.assign');
    });

Route::middleware(['auth', 'permission:workspace_config.manage_sidebar_department'])
    ->prefix('workspace-config/sidebar')->name('workspace-config.sidebar.')
    ->group(function () {
        Route::get('/', [WorkspaceConfigSidebarController::class, 'index'])->name('index');
        Route::put('/', [WorkspaceConfigSidebarController::class, 'update'])->name('update');
    });
