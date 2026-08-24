<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Example Module Manager Routes
|--------------------------------------------------------------------------
| Chỉ dùng khi module cần route riêng trong khu vực manager; mặc định
| dự án đăng ký 4 loại route ở cấp global (routes/manager.php). Nạp file
| này trong ExampleServiceProvider::boot() nếu module thực sự cần tách.
*/

Route::prefix('example')->name('example.')->group(function () {
    // Route::get('/', [ExampleController::class, 'index'])->name('index');
});
