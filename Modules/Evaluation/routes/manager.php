<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriteriaController;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriterionTypeController;

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

    Route::get('/criterion-types', [EvaluationCriterionTypeController::class, 'index'])
        ->name('criterion-types.index');

    Route::post('/criterion-types', [EvaluationCriterionTypeController::class, 'store'])
        ->name('criterion-types.store');

    // List tiêu chí — manager: phòng ban của user; superadmin: ?department_id=
    Route::get('/criteria', [EvaluationCriteriaController::class, 'index'])
        ->name('criteria.index');

    Route::get('/criteria/history', [EvaluationCriteriaController::class, 'history'])
        ->name('criteria.history');

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

    // Bật / tắt hiện trên ĐGNL
    Route::patch('/criteria/{id}/toggle-evaluation', [EvaluationCriteriaController::class, 'toggleUseInEvaluation'])
        ->name('criteria.toggle-evaluation');

    // Xoá tiêu chí
    Route::delete('/criteria/{id}', [EvaluationCriteriaController::class, 'destroy'])
        ->name('criteria.destroy');
});
