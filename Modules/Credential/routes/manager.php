<?php

use Illuminate\Support\Facades\Route;
use Modules\Credential\App\Http\Controllers\CredentialController;
use Modules\Credential\App\Http\Controllers\CredentialProviderController;
use Modules\Credential\App\Http\Controllers\CredentialViewerController;

/*
|--------------------------------------------------------------------------
| Credential Module — Manager Routes
|--------------------------------------------------------------------------
| JSON SPA dưới prefix /api (middleware web + session) — giống hệt
| ProjectServiceProvider. Trang Vue /manager/credential (SPA, phục vụ qua
| fallback trong routes/web.php gốc) ≠ path JSON /api/credential/*.
|
| Đọc (index/show) chỉ cần credential.view — Service tự ẩn field nhạy cảm
| (password/username) nếu viewer không phải creator/viewer được cấp riêng
| (bảng credential_viewers). Ghi/xoá cần credential.manage.
*/

Route::middleware(['auth', 'permission:credential.view'])
    ->prefix('credential')->name('credential.')
    ->group(function () {
        Route::get('/', [CredentialController::class, 'index'])->name('index');
        Route::get('/providers', [CredentialProviderController::class, 'index'])->name('providers.index');
        Route::get('/users', [CredentialController::class, 'users'])->name('users');
        Route::get('/{credential}', [CredentialController::class, 'show'])->name('show');
    });

Route::middleware(['auth', 'permission:credential.manage'])
    ->prefix('credential')->name('credential.')
    ->group(function () {
        Route::post('/', [CredentialController::class, 'store'])->name('store');
        Route::put('/{credential}', [CredentialController::class, 'update'])->name('update');
        Route::delete('/{credential}', [CredentialController::class, 'destroy'])->name('destroy');

        Route::post('/providers', [CredentialProviderController::class, 'store'])->name('providers.store');
        Route::put('/providers/{provider}', [CredentialProviderController::class, 'update'])->name('providers.update');
        Route::delete('/providers/{provider}', [CredentialProviderController::class, 'destroy'])->name('providers.destroy');
    });

/*
| Cấp/thu hồi quyền xem dữ liệu nhạy cảm — chỉ cần 'auth', quyền thật kiểm
| tra trong CredentialViewerController (creator hoặc credential.manage).
*/
Route::middleware(['auth'])
    ->prefix('credential')->name('credential.')
    ->group(function () {
        Route::post('/{credential}/viewers', [CredentialViewerController::class, 'store'])->name('viewers.store');
        Route::delete('/{credential}/viewers/{user}', [CredentialViewerController::class, 'destroy'])->name('viewers.destroy');
    });
