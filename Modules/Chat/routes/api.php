<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\App\Http\Controllers\ChatConversationController;
use Modules\Chat\App\Http\Controllers\ChatMessageController;

Route::middleware('auth')->prefix('chat')->name('chat.')->group(function () {
    Route::get('/conversations', [ChatConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [ChatConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversationId}/read', [ChatConversationController::class, 'markRead'])->name('conversations.read');
    Route::get('/conversations/{conversationId}/messages', [ChatMessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversationId}/messages', [ChatMessageController::class, 'store'])->name('messages.store');
    Route::get('/unread-count', [ChatConversationController::class, 'unreadCount'])->name('unread-count');
    Route::get('/users/search', [ChatConversationController::class, 'searchUsers'])->name('users.search');
});
