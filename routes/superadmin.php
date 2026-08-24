<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Superadmin Routes
|--------------------------------------------------------------------------
|
| Route dành riêng cho superadmin (toàn quyền hệ thống). Được đăng ký với
| prefix "superadmin" và name prefix "superadmin." trong
| App\Providers\RouteServiceProvider.
|
| - Luôn bọc middleware auth + role:superadmin, không dùng chung guard
|   với manager.
| - Route theo module: đặt trong Modules/{TenModule}/Routes/superadmin.php.
|
*/

Route::middleware(['auth'])->group(function () {
    // Route::get('/dashboard', [SuperadminDashboardController::class, 'index'])->name('dashboard');
});
