<?php

use Illuminate\Support\Facades\Route;
use Modules\Project\App\Http\Controllers\ProjectController;

/*
|--------------------------------------------------------------------------
| Project Module — API Routes
|--------------------------------------------------------------------------
| Prefix thật: /api/project/* (đăng ký qua ProjectServiceProvider với
| middleware web + prefix api — giống hệt EvaluationServiceProvider).
|
| Tất cả route dưới đây yêu cầu auth (middleware 'web' bọc ngoài trong
| ServiceProvider chỉ đảm bảo session, KHÔNG tự bắt đăng nhập — auth thật
| áp dụng qua middleware 'auth' + 'permission:' bên dưới).
*/

Route::middleware(['auth'])->prefix('project')->name('project.')->group(function () {

    // Danh mục giá trị cố định (type/status/importance/progress_method/scope_type)
    Route::get('/options', [ProjectController::class, 'options'])->name('options');

    // Danh sách user để chọn "Phụ trách chính" / "Người thực hiện"
    Route::get('/assignable-users', [ProjectController::class, 'assignableUsers'])->name('assignable-users');

    // Nhãn tự do (mục E) — đọc chỉ cần đăng nhập, tạo cũng vậy (gõ-tìm-tạo ngay trong form).
    Route::get('/labels', [ProjectController::class, 'labelsIndex'])->name('labels.index');
    Route::post('/labels', [ProjectController::class, 'labelsStore'])->name('labels.store');

    // Loại dự án (mục A) — chọn từ danh sách hoặc tự tạo mới ngay trong form (nút +).
    Route::get('/types', [ProjectController::class, 'typesIndex'])->name('types.index');
    Route::post('/types', [ProjectController::class, 'typesStore'])->name('types.store');

    // Cài đặt dự án (mục D + C) — chỉ admin/super_admin (project.manage_settings
    // chỉ có trong wildcard project.* của admin / '*' của super_admin, không
    // gán cho role phòng ban nào khác trong config/permissions.php).
    Route::middleware('permission:project.manage_settings')->group(function () {
        Route::get('/settings/general', [ProjectController::class, 'settingsGeneral'])->name('settings.general');
        Route::put('/settings/general', [ProjectController::class, 'settingsGeneralUpdate'])->name('settings.general.update');
        Route::get('/settings/creator-allowlist', [ProjectController::class, 'settingsCreatorAllowlist'])->name('settings.creator-allowlist');
        Route::put('/settings/creator-allowlist', [ProjectController::class, 'settingsCreatorAllowlistUpdate'])->name('settings.creator-allowlist.update');
    });

    Route::middleware('permission:project.view')->get('/', [ProjectController::class, 'index'])->name('index');
    Route::middleware('permission:project.view')->get('/export', [ProjectController::class, 'export'])->name('export');
    Route::post('/import/preview', [ProjectController::class, 'importPreview'])->name('import-preview');
    Route::post('/import/resolve-row', [ProjectController::class, 'importResolveRow'])->name('import-resolve-row');
    Route::post('/import/confirm', [ProjectController::class, 'importConfirm'])->name('import-confirm');
    Route::middleware('permission:project.view')->get('/{project}', [ProjectController::class, 'show'])->name('show');

    // store: middleware permission cứng đã bỏ — quyền tạo (role sẵn có HOẶC
    // allowlist mở rộng, mục C) được kiểm tra trong StoreProjectRequest::authorize().
    Route::post('/', [ProjectController::class, 'store'])->name('store');

    // Theo dõi dự án (mục B) — chỉ cần đăng nhập + xem được dự án (Controller
    // đã 404 nếu không tìm thấy; việc "xem được" áp dụng ở list, không chặn
    // ở follow vì follow là hành động tự nguyện của chính user).
    Route::post('/{project}/follow', [ProjectController::class, 'follow'])->name('follow');
    Route::delete('/{project}/follow', [ProjectController::class, 'unfollow'])->name('unfollow');

    // Sửa/xoá/đính kèm — dùng project.update_department: mọi role hiện có
    // sở hữu project.manage_department LUÔN đi kèm project.update_department
    // trong config/permissions.php (department_director, deputy_department_director),
    // và admin/super_admin có project.*/'*' nên vẫn pass bình thường. Middleware
    // permission: không hỗ trợ OR nhiều key nên chọn key hẹp hơn trong cặp là đủ.
    Route::middleware('permission:project.update_department')->group(function () {
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/avatar', [ProjectController::class, 'uploadAvatar'])->name('avatar');
        Route::delete('/{project}/avatar', [ProjectController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::post('/{project}/attachments', [ProjectController::class, 'uploadAttachment'])->name('attachments.store');
        Route::delete('/{project}/attachments/{attachment}', [ProjectController::class, 'destroyAttachment'])->name('attachments.destroy');
    });
});
