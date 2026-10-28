<?php

use App\Services\SpaOpenGraphResolver;
use App\Support\LinkPreviewBot;
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

$spaView = static function () {
    $request = request();

    if (LinkPreviewBot::matchesRequest($request)) {
        $meta = app(SpaOpenGraphResolver::class)->resolve($request);

        return response()
            ->view('link-preview', ['meta' => $meta])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    return response()
        ->view('app')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
};

Route::get('/', $spaView);

// /offline.html, /sw.js, /manifest.json là file tĩnh trong public/ — web server phục vụ trực tiếp
// (Nginx/Apache chỉ đẩy sang Laravel khi file không tồn tại), nên KHÔNG đăng ký route ở đây.
// Header cache của chúng đặt ở cấu hình web server: deploy/workspace.vaschools.edu.vn.conf.

Route::get('/pdfjs-worker.mjs', function () {
    $path = public_path('vendor/pdfjs/pdf.worker.min.mjs');
    if (! is_file($path)) {
        $path = base_path('node_modules/pdfjs-dist/build/pdf.worker.min.mjs');
    }
    abort_unless(is_file($path), 404);

    return response((string) file_get_contents($path), 200, [
        'Content-Type' => 'text/javascript; charset=UTF-8',
        'Cache-Control' => 'public, max-age=86400',
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
Route::fallback($spaView);