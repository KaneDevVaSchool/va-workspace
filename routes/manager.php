<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\DepartmentController;
use Modules\Identity\App\Http\Controllers\TeamController;

/*
|--------------------------------------------------------------------------
| Manager Routes
|--------------------------------------------------------------------------
|
| Route cho khu vực quản lý (manager/quản trị cấp trường, cấp phòng ban...).
| Được đăng ký với prefix "manager" và name prefix "manager." trong
| App\Providers\RouteServiceProvider.
|
| - Bọc route trong middleware auth + role/permission tương ứng
|   (vd: ->middleware(['auth', 'role:manager'])).
| - Route theo module: đặt trong Modules/{TenModule}/Routes/manager.php.
|
*/

Route::middleware(['auth'])->group(function () {
    // Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');

    // Danh sách phòng ban — dùng cho dropdown (team, scope filter...).
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');

    // Danh sách nhóm theo phòng ban (dropdown scope trên ma trận phân quyền).
    // CRUD nhóm: WorkspaceConfig members API — không còn trang /manager/teams.
    Route::middleware(['permission:team.manage'])
        ->get('/teams', [TeamController::class, 'index'])
        ->name('teams.index');
});
