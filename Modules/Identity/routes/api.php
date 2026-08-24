<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\MeController;
use Modules\Identity\App\Http\Controllers\ViewAsController;

/*
|--------------------------------------------------------------------------
| Identity Module — API Routes
|--------------------------------------------------------------------------
| auth:sanctum — xác thực qua cookie/session (Sanctum SPA), không phải
| Bearer token. SPA phải gọi GET /sanctum/csrf-cookie trước khi gọi các
| route auth:sanctum lần đầu (xem resources/js/bootstrap.js).
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class)->name('me');

    // "Xem thử" vai trò khác — chỉ super_admin (kiểm tra trong
    // ViewAsService, không phải ở route). Xem Modules/Identity/App/Services/ViewAsService.php.
    Route::post('/view-as', [ViewAsController::class, 'activate'])->name('view-as.activate');
    Route::delete('/view-as', [ViewAsController::class, 'deactivate'])->name('view-as.deactivate');
});
