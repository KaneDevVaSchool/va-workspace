<?php

use Illuminate\Support\Facades\Route;
use Modules\Project\App\Http\Controllers\CommentController;
use Modules\Project\App\Http\Controllers\ProjectController;
use Modules\Project\App\Http\Controllers\ProjectFeedbackController;
use Modules\Project\App\Http\Controllers\ProjectTestCaseController;
use Modules\Project\App\Http\Controllers\TaskAttachmentController;
use Modules\Project\App\Http\Controllers\TaskController;
use Modules\Project\App\Http\Controllers\TaskScoreController;
use Modules\Project\App\Http\Controllers\TaskWorklogController;

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

    // ---------- Task (Project Giai đoạn 2 — WBS đa cấp, thuộc project) ----------
    // Route tĩnh /tasks* PHẢI đăng ký trước GET /{project} (int binding, dòng
    // dưới) — nếu không Laravel sẽ hiểu "tasks" là giá trị {project}.
    // /tasks/export và /tasks/options cũng PHẢI đăng ký TRƯỚC GET /tasks/{task}
    // (wildcard, cùng method GET) — cùng lý do đã áp dụng cho attachments/
    // worklogs ở dưới (route tĩnh trước wildcard, tránh "export"/"options" bị
    // Laravel hiểu nhầm là giá trị {task}).
    Route::middleware('permission:task.view|task.view_assigned')->group(function () {
        Route::get('/tasks/options', [TaskController::class, 'options'])->name('tasks.options');
        Route::get('/tasks/export', [TaskController::class, 'export'])->name('tasks.export');
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::get('/{project}/tasks', [TaskController::class, 'treeByProject'])->name('tasks.tree');
    });

    Route::middleware('permission:task.create')->group(function () {
        Route::post('/tasks', [TaskController::class, 'storeStandalone'])->name('tasks.store-standalone');
        // Route tĩnh /tasks/bulk và /structure/{type} PHẢI trước wildcard {project}/tasks
        // nếu cùng prefix — nhưng bulk gắn {project} nên đăng ký cạnh store.
        Route::post('/{project}/tasks/bulk', [TaskController::class, 'storeBulk'])->name('tasks.store-bulk');
        Route::put('/{project}/structure/{type}', [TaskController::class, 'syncStructure'])
            ->whereIn('type', ['category', 'phase'])
            ->name('structure.sync');
        Route::post('/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::post('/tasks/import/preview', [TaskController::class, 'importPreview'])->name('tasks.import-preview');
        Route::post('/tasks/import/resolve-row', [TaskController::class, 'importResolveRow'])->name('tasks.import-resolve-row');
        Route::post('/tasks/import/confirm', [TaskController::class, 'importConfirm'])->name('tasks.import-confirm');
    });

    // ---------- Đính kèm công việc (Nhóm D — bản tối thiểu, chỉ file) ----------
    // DELETE /tasks/attachments/{attachment} PHẢI đăng ký TRƯỚC DELETE
    // /tasks/{task} bên dưới — cùng method DELETE, nếu {task} (wildcard)
    // đăng ký trước thì "attachments" sẽ bị Laravel hiểu nhầm là giá trị
    // {task} và route attachments không bao giờ được match tới.
    Route::middleware('permission:task.view|task.view_assigned')
        ->get('/tasks/{task}/attachments', [TaskAttachmentController::class, 'index'])
        ->name('tasks.attachments.index');
    Route::middleware('permission:task.create')->group(function () {
        Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
        Route::put('/tasks/attachments/{attachment}', [TaskAttachmentController::class, 'update'])->name('tasks.attachments.update');
        Route::post('/tasks/attachments/{attachment}/replace', [TaskAttachmentController::class, 'replace'])->name('tasks.attachments.replace');
        Route::delete('/tasks/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');
    });

    // ---------- Thảo luận công việc (Comment — polymorphic Task/Project) ----------
    // Đọc dùng task.view (đã có), tạo dùng task.create (đã có) — đúng
    // permission hiện hữu, không thêm permission mới. Route tĩnh
    // /comments/mentions PHẢI đăng ký TRƯỚC bất kỳ route GET /comments/{...}
    // wildcard nào (không có ở đây nhưng giữ thói quen của file này).
    Route::middleware('permission:task.view|task.view_assigned')->group(function () {
        Route::get('/comments/mentions', [CommentController::class, 'mentions'])->name('comments.mentions');
        Route::get('/tasks/{task}/comments', [CommentController::class, 'taskIndex'])->name('tasks.comments.index');
    });
    Route::middleware('permission:task.create')
        ->post('/tasks/{task}/comments', [CommentController::class, 'taskStore'])
        ->name('tasks.comments.store');

    // Xoá/reaction/ghim không phân theo Task/Project — chỉ cần đăng nhập,
    // quyền thật (xoá: chỉ tác giả; ghim: quyền sửa dự án) kiểm tra trong
    // CommentService::canDelete()/canPin().
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/reactions', [CommentController::class, 'setReaction'])->name('comments.reactions.set');
    Route::get('/comments/{comment}/reactions', [CommentController::class, 'reactions'])->name('comments.reactions.index');
    Route::post('/comments/{comment}/pin', [CommentController::class, 'pin'])->name('comments.pin');
    Route::delete('/comments/{comment}/pin', [CommentController::class, 'unpin'])->name('comments.unpin');

    // ---------- Worklog chấm công giờ thực tế (Nhóm E) ----------
    // PUT/DELETE /tasks/worklogs/{worklog} cùng lý do PHẢI đăng ký TRƯỚC
    // PUT/DELETE /tasks/{task} bên dưới (tránh "worklogs" bị nuốt làm {task}).
    Route::middleware('permission:task.view|task.view_assigned')
        ->get('/tasks/{task}/worklogs', [TaskWorklogController::class, 'index'])
        ->name('tasks.worklogs.index');
    Route::middleware('permission:task.create')->group(function () {
        Route::post('/tasks/{task}/worklogs', [TaskWorklogController::class, 'store'])->name('tasks.worklogs.store');
        Route::put('/tasks/worklogs/{worklog}', [TaskWorklogController::class, 'update'])->name('tasks.worklogs.update');
        Route::delete('/tasks/worklogs/{worklog}', [TaskWorklogController::class, 'destroy'])->name('tasks.worklogs.destroy');
    });

    // ---------- Đánh giá tối thiểu (Nhóm G) ----------
    // /tasks/{task}/score cùng cấu trúc /tasks/{task}/xxx như attachments/
    // worklogs ở trên — không xung đột route wildcard /tasks/{task} (khác
    // số lượng path segment), không cần lưu ý thứ tự đặc biệt.
    Route::middleware('permission:task.view|task.view_assigned')
        ->get('/tasks/{task}/score', [TaskScoreController::class, 'show'])
        ->name('tasks.score.show');
    Route::middleware('permission:task.approve')
        ->put('/tasks/{task}/score', [TaskScoreController::class, 'upsert'])
        ->name('tasks.score.upsert');

    // ---------- Báo cáo hoàn thành (assignee-only) ----------
    // Quan hệ "chỉ assignee của task" kiểm tra trong TaskService::reportComplete(),
    // không phải permission tĩnh — route cần xem được task (task.view hoặc
    // task.view_assigned). Nhân viên chỉ có view_assigned vẫn báo hoàn thành
    // được việc của mình.
    Route::middleware('permission:task.view|task.view_assigned')
        ->post('/tasks/{task}/report-complete', [TaskController::class, 'reportComplete'])
        ->name('tasks.report-complete');

    // ---------- Bulk actions (PR7) ----------
    // PATCH /tasks/bulk là route tĩnh khác method (PATCH) với PUT/DELETE
    // /tasks/{task} nên về lý thuyết không xung đột thật, nhưng đặt trước
    // cho nhất quán với cách xử lý attachments/worklogs (route tĩnh trước
    // wildcard) — tránh rủi ro nếu sau này có ai thêm PATCH /tasks/{task}.
    Route::middleware('permission:task.create')
        ->patch('/tasks/bulk', [TaskController::class, 'bulkUpdate'])
        ->name('tasks.bulk-update');

    Route::middleware('permission:task.delegate')
        ->patch('/tasks/bulk-delegate', [TaskController::class, 'bulkDelegate'])
        ->name('tasks.bulk-delegate');

    Route::middleware('permission:task.create')->group(function () {
        // update dùng implicit model binding (Task $task, khác int $task ở
        // show/destroy) — UpdateTaskRequest cần đọc progress_type HIỆN CÓ
        // của task để validate đúng ràng buộc progress_number/progress_total
        // khi client chỉ PUT một phần field (PATCH-semantics), không có cách
        // nào lấy state đó ở tầng Request nếu không có model thật.
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    });

    // Danh mục giá trị cố định (type/status/importance/progress_method/scope_type)
    Route::get('/options', [ProjectController::class, 'options'])->name('options');

    // Danh sách user để chọn "Phụ trách chính" / "Người thực hiện"
    Route::get('/assignable-users', [ProjectController::class, 'assignableUsers'])->name('assignable-users');
    Route::get('/assignable-projects', [ProjectController::class, 'assignableProjects'])->name('assignable-projects');
    Route::get('/assignable-parent-tasks', [TaskController::class, 'assignableParents'])->name('assignable-parent-tasks');

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
    Route::post('/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('duplicate');
    Route::middleware('permission:project.view')->get('/{project}/quick-items', [ProjectController::class, 'quickItemsIndex'])->name('quick-items.index');
    Route::middleware('permission:project.view')->get('/export', [ProjectController::class, 'export'])->name('export');
    Route::post('/import/preview', [ProjectController::class, 'importPreview'])->name('import-preview');
    Route::post('/import/resolve-row', [ProjectController::class, 'importResolveRow'])->name('import-resolve-row');
    Route::post('/import/confirm', [ProjectController::class, 'importConfirm'])->name('import-confirm');

    // ---------- Thảo luận dự án ----------
    // Đọc dùng project.view; TẠO chỉ cần đăng nhập + xem được project (đã
    // chốt — không permission tạo riêng). Đặt TRƯỚC GET /{project} (wildcard,
    // dòng dưới) vì cùng có tiền tố "/{project}/..." — nếu không "comments"
    // không xung đột thật (khác param count) nhưng giữ nhất quán thói quen.
    Route::middleware('permission:project.view')
        ->get('/{project}/comments', [CommentController::class, 'projectIndex'])
        ->name('comments.project-index');
    Route::post('/{project}/comments', [CommentController::class, 'projectStore'])->name('comments.project-store');
    Route::middleware('permission:project.view')
        ->post('/{project}/comments/mark-read', [CommentController::class, 'markThreadRead'])
        ->name('comments.mark-read');

    Route::middleware('permission:project.view')->get('/{project}/documents', [ProjectController::class, 'documents'])->name('documents');
    Route::middleware('permission:project.view')->get('/{project}/task-attachments', [ProjectController::class, 'taskAttachments'])->name('task-attachments');

    // ---------- Testcase (tab tuỳ chọn) ----------
    Route::middleware('permission:test_case.view')
        ->get('/{project}/test-cases', [ProjectTestCaseController::class, 'index'])
        ->name('test-cases.index');
    Route::middleware('permission:test_case.create')
        ->post('/{project}/test-cases', [ProjectTestCaseController::class, 'store'])
        ->name('test-cases.store');
    Route::middleware('permission:test_case.manage')->group(function () {
        Route::put('/test-cases/{testCase}', [ProjectTestCaseController::class, 'update'])->name('test-cases.update');
        Route::delete('/test-cases/{testCase}', [ProjectTestCaseController::class, 'destroy'])->name('test-cases.destroy');
    });
    // check1/check2/attachment: chỉ cần test_case.view — quyền thật (đúng
    // người: assignee cho check1, người tạo cho check2) kiểm tra trong
    // ProjectTestCaseService::canCheck1()/canCheck2(), không phải
    // permission tĩnh (assignee có thể chỉ có test_case.create, chưa
    // chắc có test_case.manage).
    Route::middleware('permission:test_case.view')->group(function () {
        Route::put('/test-cases/{testCase}/check1', [ProjectTestCaseController::class, 'updateCheck1'])->name('test-cases.check1');
        Route::put('/test-cases/{testCase}/check2', [ProjectTestCaseController::class, 'updateCheck2'])->name('test-cases.check2');
        Route::post('/test-cases/{testCase}/attachment', [ProjectTestCaseController::class, 'uploadAttachment'])->name('test-cases.attachment.store');
        Route::delete('/test-cases/{testCase}/attachment', [ProjectTestCaseController::class, 'destroyAttachment'])->name('test-cases.attachment.destroy');
    });

    // ---------- Phản hồi/đánh giá dự án (tab tuỳ chọn) ----------
    // Sửa/xoá không có middleware permission riêng — quyền thật (chỉ tác
    // giả) kiểm tra trong ProjectFeedbackService::canEdit(), giống hệt
    // pattern comments.destroy ở trên.
    Route::middleware('permission:feedback.view')
        ->get('/{project}/feedbacks', [ProjectFeedbackController::class, 'index'])
        ->name('feedbacks.index');
    Route::middleware('permission:feedback.create')
        ->post('/{project}/feedbacks', [ProjectFeedbackController::class, 'store'])
        ->name('feedbacks.store');
    Route::put('/feedbacks/{feedback}', [ProjectFeedbackController::class, 'update'])->name('feedbacks.update');
    Route::delete('/feedbacks/{feedback}', [ProjectFeedbackController::class, 'destroy'])->name('feedbacks.destroy');

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
    // và admin/super_admin có project.*/'*' nên vẫn pass bình thường. OR nhiều
    // key dùng dấu `|` (xem EnsureHasPermission).
    Route::middleware('permission:project.update_department')->group(function () {
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::put('/{project}/tab-config', [ProjectController::class, 'updateTabConfig'])->name('tab-config.update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{project}/avatar', [ProjectController::class, 'uploadAvatar'])->name('avatar');
        Route::delete('/{project}/avatar', [ProjectController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::post('/{project}/attachments', [ProjectController::class, 'uploadAttachment'])->name('attachments.store');
        Route::put('/{project}/attachments/{attachment}', [ProjectController::class, 'updateAttachment'])->name('attachments.update');
        Route::delete('/{project}/attachments/{attachment}', [ProjectController::class, 'destroyAttachment'])->name('attachments.destroy');
        Route::post('/{project}/folders', [ProjectController::class, 'storeFolder'])->name('folders.store');
        Route::put('/{project}/folders/{folder}', [ProjectController::class, 'updateFolder'])->name('folders.update');
        Route::delete('/{project}/folders/{folder}', [ProjectController::class, 'destroyFolder'])->name('folders.destroy');
        Route::post('/{project}/quick-items', [ProjectController::class, 'quickItemsStore'])->name('quick-items.store');
    });
});
