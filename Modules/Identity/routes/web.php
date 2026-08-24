<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\GoogleAuthController;
use Modules\Identity\App\Http\Controllers\MeController;
use Modules\Identity\App\Http\Controllers\ViewAsController;

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

// Plaintext CSRF — luôn chạy middleware web (cùng session với POST /logout).
Route::get('/csrf-token', [MeController::class, 'csrf'])->name('csrf-token');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');
});

// SPA session API — middleware web + throttle (cùng session/CSRF với logout).
Route::middleware(['auth', 'throttle:60,1'])->prefix('api')->group(function () {
    Route::get('/me', MeController::class)->name('me');
    Route::post('/view-as', [ViewAsController::class, 'activate'])->name('view-as.activate');
    Route::delete('/view-as', [ViewAsController::class, 'deactivate'])->name('view-as.deactivate');
});
