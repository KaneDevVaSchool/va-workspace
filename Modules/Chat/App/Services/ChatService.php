<?php

namespace Modules\Chat\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Chat\App\Events\InboxUpdated;
use Modules\Chat\App\Events\MessageRead;
use Modules\Chat\App\Events\MessageSent;
use Modules\Chat\App\Events\MessageUpdated;
use Modules\Chat\App\Events\UserTyping;
use Modules\Chat\App\Models\Conversation;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Repositories\Contracts\ConversationRepositoryInterface;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\App\Services\NotificationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChatService
{
    private const STICKER_ID_PATTERN = '/^[0-9a-f]{2,8}(?:_[0-9a-f]{2,8}){0,12}$/';

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
        private readonly NotificationService $notifications,
    ) {}

    public function openPrivateConversationWith(User $actor, int $otherUserId): array
    {
        if ((int) $actor->id === $otherUserId) {
            throw ValidationException::withMessages([
                'user_id' => ['Không thể tự trò chuyện với chính mình.'],
            ]);
        }

        $other = User::find($otherUserId);

        if (! $other || ! $other->isActive()) {
            throw ValidationException::withMessages([
                'user_id' => ['Người dùng không tồn tại hoặc đã ngừng hoạt động.'],
            ]);
        }

        $conversation = $this->conversations->findOrCreatePrivate($actor->id, $otherUserId);

        return $this->presentConversation($conversation, $actor);
    }

    public function listConversations(User $actor): array
    {
        $conversations = $this->conversations->listForUser($actor->id);
        $unreadCounts = $this->messages->unreadCountsForUser($actor->id);

        return $conversations
            ->map(fn (Conversation $conversation) => $this->presentConversation(
                $conversation,
                $actor,
                $unreadCounts[$conversation->id] ?? 0,
            ))
            ->values()
            ->all();
    }

    public function isMember(User $actor, int $conversationId): bool
    {
        return $this->conversations->isMember($conversationId, $actor->id);
    }

    public function listMessages(User $actor, int $conversationId, ?int $beforeId): array
    {
        $messages = $this->messages->paginateBefore($conversationId, $actor->id, $beforeId, 30);

        return [
            'messages' => $messages->map(fn (Message $message) => $this->present($message))->values()->all(),
            'has_more' => $messages->count() === 30,
        ];
    }

    /**
     * @param  array{message?: string|null, message_type?: string|null, sticker_id?: string|null, reply_to_id?: int|null}  $input
     */
    public function sendMessage(User $actor, int $conversationId, array $input): array
    {
        $type = $input['message_type'] ?? Message::TYPE_TEXT;
        if (! in_array($type, [Message::TYPE_TEXT, Message::TYPE_STICKER], true)) {
            throw ValidationException::withMessages([
                'message_type' => ['Loại tin nhắn không hợp lệ.'],
            ]);
        }

        $body = trim((string) ($input['message'] ?? ''));
        $stickerId = $type === Message::TYPE_STICKER ? trim((string) ($input['sticker_id'] ?? '')) : null;
        $files = $this->uploadedFiles($input['attachments'] ?? []);

        if ($type === Message::TYPE_STICKER) {
            if ($stickerId === '' || ! preg_match(self::STICKER_ID_PATTERN, $stickerId)) {
                throw ValidationException::withMessages([
                    'sticker_id' => ['Sticker không hợp lệ.'],
                ]);
            }
            if ($body === '') {
                $body = '🙂';
            }
            $files = [];
        } elseif ($body === '' && $files === []) {
            throw ValidationException::withMessages([
                'message' => ['Vui lòng nhập nội dung hoặc chọn tệp.'],
            ]);
        }

        $replyToId = isset($input['reply_to_id']) ? (int) $input['reply_to_id'] : null;
        if ($replyToId !== null) {
            $parent = $this->messages->findForConversation($conversationId, $replyToId);
            if (! $parent || $parent->isRecalled()) {
                throw ValidationException::withMessages([
                    'reply_to_id' => ['Tin nhắn được trả lời không còn hợp lệ.'],
                ]);
            }
        }

        $message = $this->messages->create([
            'conversation_id' => $conversationId,
            'sender_id' => $actor->id,
            'reply_to_id' => $replyToId,
            'message' => $body,
            'sticker_id' => $stickerId,
            // Cột enum hiện có chưa gồm 'sticker' — sticker nhận diện bằng sticker_id.
            'message_type' => Message::TYPE_TEXT,
        ]);

        if ($files !== []) {
            $this->storeAttachments($message, $files);
            $message = $this->messages->findForConversation($conversationId, $message->id) ?? $message;
        }

        Conversation::where('id', $conversationId)->update(['updated_at' => now()]);

        $presented = $this->present($message);

        MessageSent::dispatch($conversationId, $presented);

        $recipient = $this->conversations->otherMember($conversationId, $actor->id);

        if ($recipient?->user) {
            $unread = $this->messages->unreadCountsForUser($recipient->user->id)[$conversationId] ?? 1;
            $card = $this->presentConversation($this->requireConversation($conversationId), $recipient->user, $unread);
            InboxUpdated::dispatch($recipient->user->id, $card, $presented);

            if ((int) Cache::get($this->viewingKey($recipient->user->id)) !== $conversationId) {
                $this->upsertChatNotification(
                    $recipient->user,
                    $actor,
                    $conversationId,
                    "{$actor->name} đã gửi cho bạn một tin nhắn",
                    $this->notificationExcerpt($message),
                    $actor->avatar_url,
                );
            }
        }

        return ['message' => $presented];
    }

    public function editMessage(User $actor, int $conversationId, int $messageId, string $body): array
    {
        $message = $this->requireOwnText($actor, $conversationId, $messageId);
        $clean = trim($body);

        if ($clean === '') {
            throw ValidationException::withMessages([
                'message' => ['Nội dung không được để trống.'],
            ]);
        }

        $message->message = $clean;
        $message->edited_at = now();
        $message = $this->messages->save($message);

        $presented = $this->present($message);
        MessageUpdated::dispatch($conversationId, $presented);

        return ['message' => $presented];
    }

    public function recallMessage(User $actor, int $conversationId, int $messageId): array
    {
        $message = $this->requireOwn($actor, $conversationId, $messageId);

        if ($message->isRecalled()) {
            throw ValidationException::withMessages([
                'message' => ['Tin nhắn đã được thu hồi.'],
            ]);
        }

        $this->deleteAttachments($message);
        $message->message = '';
        $message->sticker_id = null;
        $message->recalled_at = now();
        $message = $this->messages->save($message);

        $presented = $this->present($message);
        MessageUpdated::dispatch($conversationId, $presented);

        return ['message' => $presented];
    }

    public function hideMessage(User $actor, int $conversationId, int $messageId): void
    {
        $message = $this->messages->findForConversation($conversationId, $messageId);
        if (! $message) {
            throw new NotFoundHttpException('Không tìm thấy tin nhắn.');
        }

        $this->messages->hideForUser($message->id, $actor->id);
    }

    public function markRead(User $actor, int $conversationId): void
    {
        $this->conversations->markRead($conversationId, $actor->id);
        $this->markChatNotificationsRead($actor->id, $conversationId);
        Cache::put($this->viewingKey($actor->id), $conversationId, now()->addSeconds(45));

        MessageRead::dispatch($conversationId, $actor->id, now()->toIso8601String());
    }

    public function touchViewing(User $actor, int $conversationId): void
    {
        Cache::put($this->viewingKey($actor->id), $conversationId, now()->addSeconds(45));
    }

    public function clearViewing(User $actor, int $conversationId): void
    {
        $key = $this->viewingKey($actor->id);
        if ((int) Cache::get($key) === $conversationId) {
            Cache::forget($key);
        }
    }

    public function broadcastTyping(User $actor, int $conversationId): void
    {
        Cache::put($this->viewingKey($actor->id), $conversationId, now()->addSeconds(45));
        UserTyping::dispatch($conversationId, $actor->id, $actor->name);
    }

    public function unreadSummary(User $actor): array
    {
        $counts = $this->messages->unreadCountsForUser($actor->id);

        return ['unread_total' => array_sum($counts)];
    }

    private function requireOwn(User $actor, int $conversationId, int $messageId): Message
    {
        $message = $this->messages->findForConversation($conversationId, $messageId);
        if (! $message) {
            throw new NotFoundHttpException('Không tìm thấy tin nhắn.');
        }

        if ((int) $message->sender_id !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Bạn chỉ thao tác được tin nhắn của mình.');
        }

        return $message;
    }

    private function requireOwnText(User $actor, int $conversationId, int $messageId): Message
    {
        $message = $this->requireOwn($actor, $conversationId, $messageId);

        if ($message->isRecalled() || $this->isSticker($message) || $message->message_type !== Message::TYPE_TEXT) {
            throw ValidationException::withMessages([
                'message' => ['Tin nhắn này không sửa được.'],
            ]);
        }

        return $message;
    }

    private function viewingKey(int $userId): string
    {
        return 'chat.viewing.'.$userId;
    }

    private function requireConversation(int $conversationId): Conversation
    {
        $conversation = $this->conversations->find($conversationId);
        if (! $conversation) {
            throw new NotFoundHttpException('Không tìm thấy cuộc trò chuyện.');
        }

        return $conversation;
    }

    private function markChatNotificationsRead(int $userId, int $conversationId): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->where('type', NotificationService::TYPE_CHAT_MESSAGE)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversationId)
            ->update(['read_at' => now()]);
    }

    private function upsertChatNotification(User $recipient, User $actor, int $conversationId, string $title, string $body, mixed $avatar): void
    {
        $data = [
            'conversation_id' => $conversationId,
            'push_icon' => is_string($avatar) ? $avatar : null,
            'push_tag' => 'va-chat-'.$conversationId,
        ];

        $existing = UserNotification::query()
            ->where('user_id', $recipient->id)
            ->where('type', NotificationService::TYPE_CHAT_MESSAGE)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversationId)
            ->first();

        if ($existing) {
            $existing->forceFill([
                'actor_id' => $actor->id,
                'title' => $title,
                'body' => $body,
                'url' => '/?chat='.$conversationId,
                'data' => [
                    'conversation_id' => $conversationId,
                ],
                'created_at' => now(),
            ])->save();

            return;
        }

        $this->notifications->notify(
            recipient: $recipient,
            actor: $actor,
            type: NotificationService::TYPE_CHAT_MESSAGE,
            title: $title,
            body: $body,
            url: '/?chat='.$conversationId,
            data: $data,
        );
    }

    private function notificationExcerpt(Message $message): string
    {
        if ($this->isSticker($message)) {
            return 'Đã gửi một sticker';
        }

        $text = Str::limit(trim($message->message), 140);
        if ($text === '' && $message->attachments->isNotEmpty()) {
            return $this->attachmentLabel($message);
        }

        if ($message->reply_to_id !== null) {
            return $text !== '' ? 'Đã trả lời: '.$text : 'Đã trả lời một tin nhắn';
        }

        return $text;
    }

    private function presentConversation(Conversation $conversation, User $actor, ?int $unreadCount = null): array
    {
        $other = $conversation->relationLoaded('members')
            ? $conversation->members->firstWhere('user_id', '!=', $actor->id)
            : $this->conversations->otherMember($conversation->id, $actor->id);

        $latest = $this->messages->latestVisibleForUser($conversation->id, $actor->id);

        return [
            'id' => $conversation->id,
            'other_user' => $this->presentOtherUser($other),
            'other_read_at' => $other?->last_read_at?->toIso8601String(),
            'last_message' => $latest ? [
                'id' => $latest->id,
                'message' => $this->previewText($latest),
                'message_type' => $this->publicType($latest),
                'recalled' => $latest->isRecalled(),
                'sender_id' => $latest->sender_id,
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => $unreadCount ?? 0,
        ];
    }

    private function previewText(Message $message): string
    {
        if ($message->isRecalled()) {
            return 'Tin nhắn đã được thu hồi';
        }

        if ($this->isSticker($message)) {
            return 'Sticker';
        }

        $text = trim($message->message);
        if ($text === '' && $message->relationLoaded('attachments') && $message->attachments->isNotEmpty()) {
            return $this->attachmentLabel($message);
        }

        return $message->message;
    }

    private function attachmentLabel(Message $message): string
    {
        $attachments = $message->attachments;
        if ($attachments->isNotEmpty() && $attachments->every(fn ($file) => $file->type === 'image')) {
            return $attachments->count() > 1 ? $attachments->count().' ảnh' : 'Ảnh';
        }

        return $attachments->count() > 1 ? $attachments->count().' tệp đính kèm' : 'Tệp đính kèm';
    }

    private function presentOtherUser(?ConversationMember $member): ?array
    {
        if (! $member || ! $member->user) {
            return null;
        }

        return [
            'id' => $member->user->id,
            'name' => $member->user->name,
            'avatar_url' => $member->user->avatar_url,
            'department' => $member->user->department?->name,
        ];
    }

    private function present(Message $message): array
    {
        $recalled = $message->isRecalled();

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'message' => $recalled || (! $this->isSticker($message) && trim($message->message) === '') ? null : $message->message,
            'message_type' => $recalled ? $message->message_type : $this->publicType($message),
            'sticker_id' => $recalled ? null : $message->sticker_id,
            'attachments' => $recalled ? [] : $this->presentAttachments($message),
            'edited_at' => $message->edited_at?->toIso8601String(),
            'recalled_at' => $message->recalled_at?->toIso8601String(),
            'reply_to' => $this->presentReply($message->replyTo),
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'avatar_url' => $message->sender->avatar_url,
            ],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function isSticker(Message $message): bool
    {
        return $message->sticker_id !== null && $message->sticker_id !== '';
    }

    private function publicType(Message $message): string
    {
        return $this->isSticker($message) ? Message::TYPE_STICKER : $message->message_type;
    }

    private function presentReply(?Message $reply): ?array
    {
        if ($reply === null) {
            return null;
        }

        $recalled = $reply->isRecalled();

        return [
            'id' => $reply->id,
            'message' => $recalled
                ? 'Tin nhắn đã được thu hồi'
                : ($this->isSticker($reply) ? 'Sticker' : $this->replyExcerpt($reply)),
            'message_type' => $this->publicType($reply),
            'recalled' => $recalled,
            'sender_name' => $reply->sender?->name,
        ];
    }

    /** @param  mixed  $files
     * @return list<UploadedFile>
     */
    private function uploadedFiles(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
    }

    /** @param  list<UploadedFile>  $files */
    private function storeAttachments(Message $message, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('chat/'.$message->id, 'public');
            $mime = $file->getMimeType();
            $message->attachments()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize() ?: 0,
                'mime_type' => $mime,
                'type' => in_array($mime, self::IMAGE_MIMES, true) ? 'image' : 'file',
            ]);
        }
    }

    private function deleteAttachments(Message $message): void
    {
        $message->loadMissing('attachments');
        foreach ($message->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }
        $message->attachments()->delete();
        $message->unsetRelation('attachments');
    }

    /** @return list<array{id: int, type: string, name: string, size: int, url: string}> */
    private function presentAttachments(Message $message): array
    {
        $message->loadMissing('attachments');

        return $message->attachments->map(fn ($file) => [
            'id' => $file->id,
            'type' => $file->type,
            'name' => $file->file_name,
            'size' => (int) $file->file_size,
            'url' => Storage::disk('public')->url($file->file_path),
        ])->values()->all();
    }

    private function replyExcerpt(Message $reply): string
    {
        $text = trim($reply->message);
        if ($text !== '') {
            return $text;
        }

        if ($reply->relationLoaded('attachments') && $reply->attachments->isNotEmpty()) {
            return $this->attachmentLabel($reply);
        }

        return '';
    }
}
