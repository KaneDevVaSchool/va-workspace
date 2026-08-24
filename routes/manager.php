<?php

use Illuminate\Support\Facades\Route;

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
});
