<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Route API (JSON), dùng middleware "api" + Sanctum khi cần auth.
|
| - Versioning khuyến nghị: Route::prefix('v1')->group(...).
| - Route theo module: đặt trong Modules/{TenModule}/Routes/api.php.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
