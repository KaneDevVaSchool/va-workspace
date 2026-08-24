<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Identity Module — Google Workspace SSO
|--------------------------------------------------------------------------
| Không đặt trong middleware `guest`: controller tự xử lý user đã đăng
| nhập (GoogleAuthController::redirect()). throttle vì callback thực hiện
| DB write (find-or-create user) mỗi lần gọi.
*/

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');
});
