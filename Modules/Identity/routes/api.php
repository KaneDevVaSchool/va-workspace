<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\MeController;

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
});
