<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Http\Controllers\ActivityLogController;
use Modules\Identity\App\Http\Controllers\GoogleAuthController;
use Modules\Identity\App\Http\Controllers\HrmSsoController;
use Modules\Identity\App\Http\Controllers\MeController;
use Modules\Identity\App\Http\Controllers\NotificationController;
use Modules\Identity\App\Http\Controllers\PermissionGrantController;
use Modules\Identity\App\Http\Controllers\PermissionMatrixController;
use Modules\Identity\App\Http\Controllers\ShortcutController;
use Modules\Identity\App\Http\Controllers\ViewAsController;

/*
|--------------------------------------------------------------------------
| Identity Module — VA-HRM SSO
|--------------------------------------------------------------------------
| Không đặt trong middleware `guest`: controller tự xử lý user đã đăng
| nhập (HrmSsoController::redirect()). throttle vì callback thực hiện DB
| write (find-or-create user) mỗi lần gọi.
|
| Hai lối đăng nhập song song, user tự chọn ở trang /login:
|   - VA-HRM SSO (HrmSsoController) — mặc định, lấy luôn thông tin nhân sự.
|   - Google Workspace trực tiếp (GoogleAuthController) — dùng khi HRM lỗi,
|     hoặc khi cần đổi sang tài khoản Google khác mà session HRM đang giữ
|     tài khoản cũ (HRM /sso/authorize nằm sau middleware auth nên phát JWT
|     ngay cho user đang đăng nhập, không hiện màn hình chọn tài khoản).
*/

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/hrm/redirect', [HrmSsoController::class, 'redirect'])
        ->name('auth.hrm.redirect');
    Route::get('/auth/hrm/callback', [HrmSsoController::class, 'callback'])
        ->name('auth.hrm.callback');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

// Named `login` — Laravel auth middleware / Exception Handler gọi route('login')
// khi guest hit route có `auth`. Trang thật là Vue SPA (cùng view `app`).
Route::get('/login', function () {
    return response()
        ->view('app')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->name('login');

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

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/push/vapid-key', [NotificationController::class, 'vapidPublicKey'])->name('notifications.push.vapid');
    Route::post('/notifications/push/subscribe', [NotificationController::class, 'subscribe'])->name('notifications.push.subscribe');
    Route::delete('/notifications/push/subscribe', [NotificationController::class, 'unsubscribe'])->name('notifications.push.unsubscribe');

    Route::get('/shortcuts', [ShortcutController::class, 'index'])->name('shortcuts.index');
    Route::post('/shortcuts', [ShortcutController::class, 'store'])->name('shortcuts.store');
    Route::put('/shortcuts/{shortcut}', [ShortcutController::class, 'update'])->name('shortcuts.update');
    Route::patch('/shortcuts/{shortcut}/favorite', [ShortcutController::class, 'toggleFavorite'])->name('shortcuts.favorite');
    Route::delete('/shortcuts/{shortcut}', [ShortcutController::class, 'destroy'])->name('shortcuts.destroy');

    Route::middleware(['role:super_admin,admin', 'menu.not_hidden:superadmin.activity'])->prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/recent', [ActivityLogController::class, 'recent'])->name('recent');
        Route::get('/options', [ActivityLogController::class, 'options'])->name('options');
        Route::get('/export', [ActivityLogController::class, 'export'])->name('export');
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
    });

    // Ma trận phân quyền (superadmin/permissions) — cùng session/CSRF với
    // phần còn lại của SPA, đặt ở đây (không phải routes/api.php module,
    // vốn dùng middleware 'api' stateless và không hoạt động với session
    // hiện tại) để nhất quán với /api/me, /api/view-as.
    Route::middleware(['role:super_admin'])->prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/matrix', [PermissionMatrixController::class, 'matrix'])->name('matrix');
        Route::put('/grants', [PermissionGrantController::class, 'upsert'])->name('grants.upsert');
        Route::put('/grants/bulk', [PermissionGrantController::class, 'bulkUpsert'])->name('grants.bulk');
        Route::delete('/grants', [PermissionGrantController::class, 'destroy'])->name('grants.destroy');
    });
});
