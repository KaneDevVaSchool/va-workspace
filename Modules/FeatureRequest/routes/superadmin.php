<?php

use Illuminate\Support\Facades\Route;
use Modules\FeatureRequest\App\Http\Controllers\FeatureRequestController;

/*
|--------------------------------------------------------------------------
| Feature Request — routes xử lý/duyệt (superadmin)
|--------------------------------------------------------------------------
| JSON SPA dưới prefix /api (middleware web + session). Quyền
| feature_request.review kiểm trong Controller, tương tự Evaluation.
*/

Route::prefix('superadmin/feature-requests')->name('superadmin.feature_request.')->group(function () {
    Route::get('/', [FeatureRequestController::class, 'indexForSuperAdmin'])->name('index');
    Route::get('/{id}', [FeatureRequestController::class, 'show'])->name('show');
    Route::patch('/{id}/approve', [FeatureRequestController::class, 'approve'])->name('approve');
    Route::patch('/{id}/reject', [FeatureRequestController::class, 'reject'])->name('reject');
    Route::patch('/{id}/done', [FeatureRequestController::class, 'markDone'])->name('done');
});
