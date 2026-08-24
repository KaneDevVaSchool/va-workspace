<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriteriaController;

/*
|--------------------------------------------------------------------------
| Evaluation Module — Manager Routes
|--------------------------------------------------------------------------
| Prefix thật: /api/evaluation/* (đăng ký qua EvaluationServiceProvider
| với middleware web + prefix api — giống WorkspaceConfig).
|
| Tất cả route dưới đây yêu cầu auth (middleware bọc ngoài trong ServiceProvider).
| Kiểm tra permission evaluation.manage_department + scope department nằm
| trong Controller (giống WorkspaceConfigMemberController).
*/

Route::prefix('evaluation')->name('evaluation.')->group(function () {

    // List tiêu chí — manager: phòng ban của user; superadmin: ?department_id=
    Route::get('/criteria', [EvaluationCriteriaController::class, 'index'])
        ->name('criteria.index');

    // Tạo tiêu chí mới (permission: evaluation.manage_department)
    Route::post('/criteria', [EvaluationCriteriaController::class, 'store'])
        ->name('criteria.store');

    // Sửa thứ tự toàn bộ danh sách (reorder)
    Route::post('/criteria/reorder', [EvaluationCriteriaController::class, 'reorder'])
        ->name('criteria.reorder');

    // Cập nhật tiêu chí
    Route::put('/criteria/{id}', [EvaluationCriteriaController::class, 'update'])
        ->name('criteria.update');

    // Bật / tắt is_active
    Route::patch('/criteria/{id}/toggle', [EvaluationCriteriaController::class, 'toggle'])
        ->name('criteria.toggle');

    // Xoá tiêu chí
    Route::delete('/criteria/{id}', [EvaluationCriteriaController::class, 'destroy'])
        ->name('criteria.destroy');
});
