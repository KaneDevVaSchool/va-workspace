<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\App\Http\Controllers\SocialCommentController;
use Modules\Social\App\Http\Controllers\SocialPollController;
use Modules\Social\App\Http\Controllers\SocialPostController;

Route::middleware('auth')->prefix('social')->name('social.')->group(function () {
    Route::get('/me/stats', [SocialPostController::class, 'meStats']);
    Route::get('/walls/{userId}', [SocialPostController::class, 'wall']);
    Route::get('/posts', [SocialPostController::class, 'index']);
    Route::post('/posts', [SocialPostController::class, 'store']);
    Route::put('/posts/{postId}', [SocialPostController::class, 'update']);
    Route::get('/posts/{postId}/revisions', [SocialPostController::class, 'revisions']);
    Route::delete('/posts/{postId}', [SocialPostController::class, 'destroy']);
    Route::post('/posts/{postId}/reactions', [SocialPostController::class, 'setReaction']);
    Route::get('/posts/{postId}/reactions', [SocialPostController::class, 'reactions']);
    Route::post('/posts/{postId}/share', [SocialPostController::class, 'share']);
    Route::post('/posts/{postId}/pin', [SocialPostController::class, 'pin']);
    Route::delete('/posts/{postId}/pin', [SocialPostController::class, 'unpin']);
    Route::get('/pinned', [SocialPostController::class, 'pinned']);

    Route::post('/posts/{postId}/poll/votes', [SocialPollController::class, 'vote']);
    Route::get('/posts/{postId}/poll/votes', [SocialPollController::class, 'voters']);
    Route::post('/posts/{postId}/poll/close', [SocialPollController::class, 'close']);

    Route::get('/mentions', [SocialCommentController::class, 'mentions']);
    Route::get('/posts/{postId}/comments', [SocialCommentController::class, 'index']);
    Route::post('/posts/{postId}/comments', [SocialCommentController::class, 'store']);
    Route::delete('/comments/{commentId}', [SocialCommentController::class, 'destroy']);
    Route::post('/comments/{commentId}/reactions', [SocialCommentController::class, 'setReaction']);
    Route::get('/comments/{commentId}/reactions', [SocialCommentController::class, 'reactions']);
});
