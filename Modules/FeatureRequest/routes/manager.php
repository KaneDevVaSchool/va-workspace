<?php

use Illuminate\Support\Facades\Route;
use Modules\FeatureRequest\App\Http\Controllers\FeatureRequestController;

/*
|--------------------------------------------------------------------------
| Feature Request — routes dùng chung cho mọi user đã đăng nhập
|--------------------------------------------------------------------------
| JSON SPA dưới prefix /api (middleware web + session). Bất kỳ ai đã đăng
| nhập cũng gọi được (kiểm quyền feature_request.create trong Controller).
*/

Route::prefix('feature-requests')->name('feature_request.')->group(function () {
    Route::get('/mine', [FeatureRequestController::class, 'mine'])->name('mine');
    Route::post('/', [FeatureRequestController::class, 'store'])->name('store');
    Route::put('/{id}', [FeatureRequestController::class, 'update'])->name('update');
    Route::delete('/{id}', [FeatureRequestController::class, 'destroy'])->name('destroy');
});
