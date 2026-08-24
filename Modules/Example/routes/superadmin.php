<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Example Module Superadmin Routes
|--------------------------------------------------------------------------
| Tương tự manager.php: chỉ dùng khi module cần tách route superadmin riêng.
*/

Route::prefix('example')->name('example.')->group(function () {
    // Route::get('/', [ExampleController::class, 'index'])->name('index');
});
