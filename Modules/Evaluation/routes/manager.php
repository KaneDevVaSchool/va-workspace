<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriteriaController;
use Modules\Evaluation\App\Http\Controllers\EvaluationCriterionTypeController;
use Modules\Evaluation\App\Http\Controllers\EvaluationPositionController;
use Modules\Evaluation\App\Http\Controllers\EvaluationScoreKitController;
use Modules\Evaluation\App\Http\Controllers\EvaluationTemplateController;

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

    // ── Mẫu đánh giá (Giai đoạn C) — xem plans/2026-08-26-mau-danh-gia.md ──
    // Mục sidebar riêng "Mẫu đánh giá" (manager.evaluation-templates.index)
    // không dùng chung path với menu khác — bọc menu.not_hidden để superadmin
    // ẩn được menu này TOÀN HỆ THỐNG (xem GlobalMenuVisibilityService).
    Route::middleware('menu.not_hidden:manager.evaluation-templates.index')->group(function () {
        // List mẫu — manager: phòng ban user + mọi mẫu is_global; superadmin: ?department_id=
        Route::get('/templates', [EvaluationTemplateController::class, 'index'])
            ->name('templates.index');

        // Tiêu chí active MỌI phòng ban — build mẫu is_global (PR4). Đặt TRƯỚC
        // /templates/{id} để không bị Laravel match nhầm thành route có param.
        Route::get('/templates/global-criteria-pool', [EvaluationTemplateController::class, 'globalCriteriaPool'])
            ->name('templates.global-criteria-pool');

        // Xuất Excel theo bộ lọc hiện tại (PR6) — CHỈ xuất, không có Nhập lại
        // cho Mẫu đánh giá. Đặt TRƯỚC /templates/{id} cùng lý do trên.
        Route::get('/templates/export', [EvaluationTemplateController::class, 'export'])
            ->name('templates.export');

        Route::get('/templates/{id}', [EvaluationTemplateController::class, 'show'])
            ->name('templates.show');

        // Tạo mẫu mới (permission: evaluation.manage_department)
        Route::post('/templates', [EvaluationTemplateController::class, 'store'])
            ->name('templates.store');

        // Cập nhật mẫu
        Route::put('/templates/{id}', [EvaluationTemplateController::class, 'update'])
            ->name('templates.update');

        // Bật / tắt is_active
        Route::patch('/templates/{id}/toggle', [EvaluationTemplateController::class, 'toggle'])
            ->name('templates.toggle');

        // Bật / tắt dùng chung toàn hệ thống (permission: evaluation.manage_global_template)
        Route::patch('/templates/{id}/toggle-global', [EvaluationTemplateController::class, 'toggleGlobal'])
            ->name('templates.toggle-global');

        // Nhân bản mẫu
        Route::post('/templates/{id}/duplicate', [EvaluationTemplateController::class, 'duplicate'])
            ->name('templates.duplicate');

        // Xoá mẫu
        Route::delete('/templates/{id}', [EvaluationTemplateController::class, 'destroy'])
            ->name('templates.destroy');
    });

    // ── Vị trí đánh giá — danh mục dùng chung toàn hệ thống, CHỈ ĐỌC.
    // Không còn tạo/sửa/xoá tay ở đây — sẽ nối API VA-HRM sau này.
    Route::get('/positions', [EvaluationPositionController::class, 'index'])
        ->name('positions.index');
});
