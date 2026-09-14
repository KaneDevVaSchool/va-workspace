<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\App\Http\Controllers\CompanyDashboardController;
use Modules\Dashboard\App\Http\Controllers\DepartmentDashboardController;
use Modules\Dashboard\App\Http\Controllers\MyDashboardController;

/*
|--------------------------------------------------------------------------
| Dashboard Module — Manager Routes
|--------------------------------------------------------------------------
| Prefix thật: /api/dashboard/* (đăng ký qua DashboardServiceProvider với
| middleware web + prefix api — giống Report/Evaluation).
|
| Dashboard tổng công ty: dashboard.view_company (super_admin qua '*', admin
| qua 'dashboard.*', director_officer qua key riêng — xem config/permissions.php).
| Dashboard phòng ban: performance.view_department HOẶC project.view — OR đủ
| 2 key có sẵn phủ đúng 4 role (department_director/deputy/section_head/
| team_lead) + role cao hơn. Phạm vi phòng ban chính xác được Controller tự
| chặn thêm (viewer chỉ xem phòng ban của mình trừ khi có quyền toàn cục).
|
| Dashboard cá nhân (me): dashboard.view — permission có sẵn, gán cho hầu
| hết mọi role kể cả viewer. Luôn scope theo chính người đăng nhập
| (MyDashboardController không nhận tham số user nào từ request).
|
| Route tĩnh (/projects, /employees) đặt trước route wildcard (/{project},
| /{user}) — đúng convention của Project/Report.
*/

Route::middleware(['auth'])->prefix('dashboard')->group(function () {

    Route::middleware('permission:dashboard.view_company')->prefix('company')->name('company.')->group(function () {
        Route::get('/overview', [CompanyDashboardController::class, 'overview'])->name('overview');
        Route::get('/projects', [CompanyDashboardController::class, 'projects'])->name('projects');
        Route::get('/projects/{project}', [CompanyDashboardController::class, 'projectDetail'])
            ->whereNumber('project')
            ->name('projects.show');
    });

    Route::middleware('permission:performance.view_department|project.view')->prefix('department')->name('department.')->group(function () {
        Route::get('/overview', [DepartmentDashboardController::class, 'overview'])->name('overview');
        Route::get('/employees', [DepartmentDashboardController::class, 'employees'])->name('employees');
        Route::get('/employees/{user}', [DepartmentDashboardController::class, 'employeeDetail'])
            ->whereNumber('user')
            ->name('employees.show');
    });

    Route::middleware('permission:dashboard.view')->prefix('me')->name('me.')->group(function () {
        Route::get('/overview', [MyDashboardController::class, 'overview'])->name('overview');
    });
});
