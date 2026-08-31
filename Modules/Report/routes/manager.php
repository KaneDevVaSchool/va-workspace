<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Report Module — Manager Routes
|--------------------------------------------------------------------------
| Prefix thật: /api/report/* (đăng ký qua ReportServiceProvider với
| middleware web + prefix api — giống Evaluation / WorkspaceConfig).
|
| Kiểm tra quyền nằm trong Controller: report.manage_department cho phòng ban
| sở hữu báo cáo, hoặc có tên trong danh sách người được chia sẻ.
*/

Route::prefix('report')->name('report.')->group(function () {

    Route::get('/', [ReportController::class, 'index'])
        ->name('index');

    Route::post('/personnel-evaluation', [ReportController::class, 'storePersonnelEvaluation'])
        ->name('personnel-evaluation.store');

    // Đặt trước /{id} vì whereNumber không bắt "personnel-evaluation" nhưng
    // để cạnh nhau cho dễ đọc theo nhóm chức năng.
    Route::post('/personnel-evaluation/preview', [ReportController::class, 'previewPersonnelEvaluation'])
        ->name('personnel-evaluation.preview');

    Route::get('/{id}', [ReportController::class, 'show'])
        ->whereNumber('id')
        ->name('show');

    Route::put('/{id}', [ReportController::class, 'update'])
        ->whereNumber('id')
        ->name('update');

    Route::patch('/{id}/save', [ReportController::class, 'save'])
        ->whereNumber('id')
        ->name('save');

    Route::get('/{id}/employees/{userId}', [ReportController::class, 'employeeDetail'])
        ->whereNumber('id')
        ->whereNumber('userId')
        ->name('employee-detail');

    Route::delete('/{id}', [ReportController::class, 'destroy'])
        ->whereNumber('id')
        ->name('destroy');
});
