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

$spaView = static fn () => response()
    ->view('app')
    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
    ->header('Pragma', 'no-cache')
    ->header('Expires', '0');

Route::get('/', $spaView);

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

    $version = (string) config('app.pwa_icon_version', '1');
    $appendIconVersion = static function (string $src) use ($version): string {
        if ($src === '' || str_contains($src, '?')) {
            return $src;
        }

        return $src.'?v='.rawurlencode($version);
    };

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    if (! empty($manifest['icons']) && is_array($manifest['icons'])) {
        foreach ($manifest['icons'] as $i => $icon) {
            if (is_array($icon) && isset($icon['src']) && is_string($icon['src'])) {
                $manifest['icons'][$i]['src'] = $appendIconVersion($icon['src']);
            }
        }
    }

    if (! empty($manifest['shortcuts']) && is_array($manifest['shortcuts'])) {
        foreach ($manifest['shortcuts'] as $si => $shortcut) {
            if (! is_array($shortcut) || empty($shortcut['icons']) || ! is_array($shortcut['icons'])) {
                continue;
            }
            foreach ($shortcut['icons'] as $ii => $icon) {
                if (is_array($icon) && isset($icon['src']) && is_string($icon['src'])) {
                    $manifest['shortcuts'][$si]['icons'][$ii]['src'] = $appendIconVersion($icon['src']);
                }
            }
        }
    }

    return response()->json($manifest, 200, [
        'Content-Type' => 'application/manifest+json; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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