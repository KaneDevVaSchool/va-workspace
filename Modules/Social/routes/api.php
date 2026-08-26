<?php

use Illuminate\Support\Facades\Route;
use Modules\Social\App\Http\Controllers\SocialCommentController;
use Modules\Social\App\Http\Controllers\SocialGroupController;
use Modules\Social\App\Http\Controllers\SocialHashtagController;
use Modules\Social\App\Http\Controllers\SocialPollController;
use Modules\Social\App\Http\Controllers\SocialPostController;

Route::middleware('auth')->prefix('social')->name('social.')->group(function () {
    Route::get('/me/stats', [SocialPostController::class, 'meStats']);

    Route::get('/groups', [SocialGroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [SocialGroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/mine/requests', [SocialGroupController::class, 'myJoinRequests'])->name('groups.my-requests');
    Route::delete('/groups/requests/{requestId}', [SocialGroupController::class, 'cancelJoinRequest'])->name('groups.cancel-request');
    Route::post('/groups/invites/{requestId}/accept', [SocialGroupController::class, 'acceptInvite'])->name('groups.invites.accept');
    Route::post('/groups/invites/{requestId}/decline', [SocialGroupController::class, 'declineInvite'])->name('groups.invites.decline');
    Route::get('/groups/{groupId}', [SocialGroupController::class, 'show'])->name('groups.show');
    Route::put('/groups/{groupId}', [SocialGroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{groupId}', [SocialGroupController::class, 'destroy'])->name('groups.destroy');
    Route::post('/groups/{groupId}/join', [SocialGroupController::class, 'join'])->name('groups.join');
    Route::post('/groups/{groupId}/leave', [SocialGroupController::class, 'leave'])->name('groups.leave');
    Route::get('/groups/{groupId}/members', [SocialGroupController::class, 'members'])->name('groups.members');
    Route::post('/groups/{groupId}/invites', [SocialGroupController::class, 'invite'])->name('groups.invites.store');
    Route::delete('/groups/{groupId}/members/{userId}', [SocialGroupController::class, 'removeMember'])->name('groups.members.remove');
    Route::put('/groups/{groupId}/members/{userId}/role', [SocialGroupController::class, 'updateMemberRole'])->name('groups.members.role');
    Route::post('/groups/{groupId}/members/{userId}/transfer-ownership', [SocialGroupController::class, 'transferOwnership'])->name('groups.transfer-ownership');
    Route::get('/groups/{groupId}/requests', [SocialGroupController::class, 'joinRequests'])->name('groups.requests.index');
    Route::post('/groups/{groupId}/requests/{requestId}/approve', [SocialGroupController::class, 'approveJoinRequest'])->name('groups.requests.approve');
    Route::post('/groups/{groupId}/requests/{requestId}/reject', [SocialGroupController::class, 'rejectJoinRequest'])->name('groups.requests.reject');
    Route::get('/walls/{userId}', [SocialPostController::class, 'wall']);
    Route::get('/posts', [SocialPostController::class, 'index']);
    Route::post('/posts', [SocialPostController::class, 'store']);
    Route::get('/posts/{postId}', [SocialPostController::class, 'show']);
    Route::put('/posts/{postId}', [SocialPostController::class, 'update']);
    Route::get('/posts/{postId}/revisions', [SocialPostController::class, 'revisions']);
    Route::delete('/posts/{postId}', [SocialPostController::class, 'destroy']);
    Route::post('/posts/{postId}/reactions', [SocialPostController::class, 'setReaction']);
    Route::get('/posts/{postId}/reactions', [SocialPostController::class, 'reactions']);
    Route::post('/posts/{postId}/view', [SocialPostController::class, 'recordView']);
    Route::post('/posts/{postId}/share', [SocialPostController::class, 'share']);
    Route::post('/posts/{postId}/pin', [SocialPostController::class, 'pin']);
    Route::delete('/posts/{postId}/pin', [SocialPostController::class, 'unpin']);
    Route::get('/pinned', [SocialPostController::class, 'pinned']);

    Route::post('/posts/{postId}/poll/votes', [SocialPollController::class, 'vote']);
    Route::get('/posts/{postId}/poll/votes', [SocialPollController::class, 'voters']);
    Route::post('/posts/{postId}/poll/close', [SocialPollController::class, 'close']);

    Route::get('/hashtags', [SocialHashtagController::class, 'index']);
    Route::get('/mentions', [SocialCommentController::class, 'mentions']);
    Route::get('/posts/{postId}/comments', [SocialCommentController::class, 'index']);
    Route::post('/posts/{postId}/comments', [SocialCommentController::class, 'store']);
    Route::delete('/comments/{commentId}', [SocialCommentController::class, 'destroy']);
    Route::post('/comments/{commentId}/reactions', [SocialCommentController::class, 'setReaction']);
    Route::get('/comments/{commentId}/reactions', [SocialCommentController::class, 'reactions']);
});
