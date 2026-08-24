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
