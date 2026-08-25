<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Public / Guest)
|--------------------------------------------------------------------------
|
| Route công khai, không yêu cầu quyền quản trị: trang chủ, landing page,
| đăng nhập/đăng ký, các trang public khác.
|
| - Route theo module: đặt trong Modules/{TenModule}/Routes/web.php và
|   require lại tại đây, hoặc để nwidart/laravel-modules tự nạp.
| - KHÔNG đặt route quản lý (manager) hay superadmin ở đây.
|
*/

Route::get('/', function () {
    return view('app');
});

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    abort_unless(is_file($path), 404);

    return response((string) file_get_contents($path), 200, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    abort_unless(is_file($path), 404);

    return response((string) file_get_contents($path), 200, [
        'Content-Type' => 'application/manifest+json; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

/*
| Fallback SPA: mọi path còn lại (chưa khớp route Laravel nào ở trên,
| và không thuộc /api, /manager, /superadmin, /auth/*, /storage...) đều
| trả về cùng view "app" để Vue Router (createWebHistory) tự nhận path
| và render đúng trang — bắt buộc để load thẳng URL như /login,
| /auth/callback (Google OAuth redirect full-page) không bị 404, và để
| F5/refresh giữa chừng trên bất kỳ route SPA nào cũng hoạt động.
| Đặt cuối cùng để không nuốt route thật (VD callback GET /auth/google
| đăng ký ở Modules/Identity/routes/web.php vẫn được match trước).
*/
Route::fallback(function () {
    return view('app');
});