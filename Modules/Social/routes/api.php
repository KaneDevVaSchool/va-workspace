<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\App\Http\Controllers\SocialCommentController;
use Modules\Social\App\Http\Controllers\SocialPostController;

Route::middleware('auth')->prefix('social')->name('social.')->group(function () {
    Route::get('/posts', [SocialPostController::class, 'index']);
    Route::post('/posts', [SocialPostController::class, 'store']);
    Route::put('/posts/{postId}', [SocialPostController::class, 'update']);
    Route::delete('/posts/{postId}', [SocialPostController::class, 'destroy']);
    Route::post('/posts/{postId}/reactions', [SocialPostController::class, 'setReaction']);
    Route::post('/posts/{postId}/share', [SocialPostController::class, 'share']);
    Route::post('/posts/{postId}/pin', [SocialPostController::class, 'pin']);
    Route::delete('/posts/{postId}/pin', [SocialPostController::class, 'unpin']);
    Route::get('/pinned', [SocialPostController::class, 'pinned']);

    Route::get('/mentions', [SocialCommentController::class, 'mentions']);
    Route::get('/posts/{postId}/comments', [SocialCommentController::class, 'index']);
    Route::post('/posts/{postId}/comments', [SocialCommentController::class, 'store']);
    Route::delete('/comments/{commentId}', [SocialCommentController::class, 'destroy']);
    Route::post('/comments/{commentId}/reactions', [SocialCommentController::class, 'setReaction']);
});
