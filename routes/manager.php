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

    // CRUD Team theo phòng ban — quyền team.manage (department_director/team_lead...).
    // Middleware chỉ chặn thô ở mức "role có key team.manage ở đâu đó";
    // TeamController tự kiểm tra lại đúng scope department cụ thể (xem
    // TeamController::denyUnlessCanManage — department_id đến từ query/body
    // chứ không phải route param nên middleware permission:...,department
    // không tự lấy được scope_id).
    Route::middleware(['permission:team.manage'])
        ->prefix('teams')->name('teams.')
        ->group(function () {
            Route::get('/', [TeamController::class, 'index'])->name('index');
            Route::post('/', [TeamController::class, 'store'])->name('store');
            Route::put('/{team}', [TeamController::class, 'update'])->name('update');
            Route::delete('/{team}', [TeamController::class, 'destroy'])->name('destroy');
        });
});
