<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\App\Http\Controllers\ChatAttachmentController;
use Modules\Chat\App\Http\Controllers\ChatConversationController;
use Modules\Chat\App\Http\Controllers\ChatMessageController;

Route::middleware('auth')->prefix('chat')->name('chat.')->group(function () {
    Route::get('/conversations', [ChatConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [ChatConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/groups', [ChatConversationController::class, 'storeGroup'])->name('conversations.groups.store');
    Route::post('/presence', [ChatConversationController::class, 'presence'])->name('presence');
    Route::get('/attachments/{attachmentId}', [ChatAttachmentController::class, 'show'])->name('attachments.show');
    Route::post('/conversations/{conversationId}/members', [ChatConversationController::class, 'addMembers'])->name('conversations.members.store');
    Route::delete('/conversations/{conversationId}/members/{userId}', [ChatConversationController::class, 'removeMember'])->name('conversations.members.destroy');
    Route::post('/conversations/{conversationId}/transfer', [ChatConversationController::class, 'transferOwner'])->name('conversations.transfer');
    Route::post('/conversations/{conversationId}/pin', [ChatConversationController::class, 'pin'])->name('conversations.pin');
    Route::post('/conversations/{conversationId}/mute', [ChatConversationController::class, 'mute'])->name('conversations.mute');
    Route::post('/conversations/{conversationId}/disappear', [ChatConversationController::class, 'disappear'])->name('conversations.disappear');
    Route::post('/conversations/{conversationId}/pinned-message', [ChatConversationController::class, 'pinMessage'])->name('conversations.pinned-message');
    Route::post('/conversations/{conversationId}/read', [ChatConversationController::class, 'markRead'])->name('conversations.read');
    Route::post('/conversations/{conversationId}/typing', [ChatConversationController::class, 'typing'])->name('conversations.typing');
    Route::post('/conversations/{conversationId}/viewing', [ChatConversationController::class, 'viewing'])->name('conversations.viewing');
    Route::delete('/conversations/{conversationId}/viewing', [ChatConversationController::class, 'leaveViewing'])->name('conversations.viewing.leave');
    Route::delete('/conversations/{conversationId}/history', [ChatConversationController::class, 'clearHistory'])->name('conversations.history.destroy');
    Route::delete('/conversations/{conversationId}', [ChatConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::get('/conversations/{conversationId}/media', [ChatMessageController::class, 'media'])->name('messages.media');
    Route::get('/conversations/{conversationId}/messages', [ChatMessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversationId}/messages', [ChatMessageController::class, 'store'])->name('messages.store');
    Route::patch('/conversations/{conversationId}/messages/{messageId}', [ChatMessageController::class, 'update'])->name('messages.update');
    Route::post('/conversations/{conversationId}/messages/{messageId}/recall', [ChatMessageController::class, 'recall'])->name('messages.recall');
    Route::post('/conversations/{conversationId}/messages/{messageId}/vote', [ChatMessageController::class, 'vote'])->name('messages.vote');
    Route::delete('/conversations/{conversationId}/messages/{messageId}', [ChatMessageController::class, 'destroy'])->name('messages.destroy');
    Route::get('/unread-count', [ChatConversationController::class, 'unreadCount'])->name('unread-count');
    Route::get('/users/search', [ChatConversationController::class, 'searchUsers'])->name('users.search');
});
