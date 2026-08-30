<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriteriaController;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriterionTypeController;
use Modules\Evaluation\App\Http\Controllers\EvaluationPositionController;
use Modules\Evaluation\App\Http\Controllers\EvaluationScoreKitController;

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

    Route::get('/score-kit', [EvaluationScoreKitController::class, 'show'])
        ->name('score-kit.show');

    Route::put('/score-kit', [EvaluationScoreKitController::class, 'update'])
        ->name('score-kit.update');

    Route::get('/criterion-types', [EvaluationCriterionTypeController::class, 'index'])
        ->name('criterion-types.index');

    Route::post('/criterion-types', [EvaluationCriterionTypeController::class, 'store'])
        ->name('criterion-types.store');

    // List tiêu chí — manager: phòng ban của user; superadmin: ?department_id=
    Route::get('/criteria', [EvaluationCriteriaController::class, 'index'])
        ->name('criteria.index');

    Route::get('/criteria/history', [EvaluationCriteriaController::class, 'history'])
        ->name('criteria.history');

    // Xuất Excel theo bộ lọc hiện tại — mọi người xem được trang đều xuất được
    Route::get('/criteria/export', [EvaluationCriteriaController::class, 'export'])
        ->name('criteria.export');

    // Xuất PDF theo bộ lọc hiện tại — cùng điều kiện với export Excel
    Route::get('/criteria/export-pdf', [EvaluationCriteriaController::class, 'exportPdf'])
        ->name('criteria.export-pdf');

    // Đọc + xem trước file Excel, KHÔNG ghi DB (permission: evaluation.manage_department)
    Route::post('/criteria/import/preview', [EvaluationCriteriaController::class, 'importPreview'])
        ->name('criteria.import-preview');

    // Xác nhận nhập — nhận JSON các dòng đã preview, ghi DB thật (permission: evaluation.manage_department)
    Route::post('/criteria/import/confirm', [EvaluationCriteriaController::class, 'importConfirm'])
        ->name('criteria.import-confirm');

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

    Route::patch('/criteria/{id}/toggle-task-type', [EvaluationCriteriaController::class, 'toggleUseForTaskType'])
        ->name('criteria.toggle-task-type');

    Route::patch('/criteria/{id}/task-score-levels', [EvaluationCriteriaController::class, 'updateTaskScoreLevels'])
        ->name('criteria.task-score-levels');

    // Xoá tiêu chí
    Route::delete('/criteria/{id}', [EvaluationCriteriaController::class, 'destroy'])
        ->name('criteria.destroy');

    // ── Vị trí đánh giá — danh mục dùng chung toàn hệ thống, CHỈ ĐỌC.
    // Không còn tạo/sửa/xoá tay ở đây — sẽ nối API VA-HRM sau này.
    Route::get('/positions', [EvaluationPositionController::class, 'index'])
        ->name('positions.index');
});
