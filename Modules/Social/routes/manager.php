<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\App\Http\Controllers\SocialPostModerationController;

/*
|--------------------------------------------------------------------------
| Social Module — Manager Routes (JSON API cho khu vực quản lý)
|--------------------------------------------------------------------------
| Trang duyệt bài viết (/manager/social/moderation phía Vue) — áp dụng toàn
| trường, bất kỳ ai có quyền `social.review` (mặc định admin/super_admin,
| hoặc được cấp thêm qua ma trận phân quyền).
|
| Prefix thật: /api/social/moderation/* (SocialServiceProvider), để F5 trang
| Vue /manager/social/moderation không bị Laravel trả JSON/404.
*/

Route::middleware(['auth', 'permission:social.review', 'menu.not_hidden:manager.social.moderation'])
    ->prefix('social/moderation')->name('social.moderation.')
    ->group(function () {
        Route::get('/', [SocialPostModerationController::class, 'index'])->name('index');
        Route::post('/{postId}/approve', [SocialPostModerationController::class, 'approve'])->name('approve');
        Route::post('/{postId}/reject', [SocialPostModerationController::class, 'reject'])->name('reject');
    });
