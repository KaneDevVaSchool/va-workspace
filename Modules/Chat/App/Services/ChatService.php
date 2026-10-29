<?php

namespace Modules\Chat\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Chat\App\Events\ConversationRemoved;
use Modules\Chat\App\Events\InboxUpdated;
use Modules\Chat\App\Events\MessageRead;
use Modules\Chat\App\Events\MessageSent;
use Modules\Chat\App\Events\MessageUpdated;
use Modules\Chat\App\Events\UserTyping;
use Modules\Chat\App\Models\ChatUserMood;
use Modules\Chat\App\Models\Conversation;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Models\MessageAttachment;
use Modules\Chat\App\Repositories\Contracts\ConversationRepositoryInterface;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Services\NotificationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChatService
{
    use HandlesChatFeatures;

    private const STICKER_ID_PATTERN = '/^(?:[0-9a-f]{2,8}(?:_[0-9a-f]{2,8}){0,12}|va_(?:0[1-9]|1[0-9]|20))$/';

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** @var list<string> */
    public const MOODS = ['coffee', 'book', 'run', 'lunch', 'focus'];

    /** @var list<string> */
    public const ACCENTS = ['coral', 'gold', 'teal', 'violet', 'leaf'];

    /** @var array<int, int>|null */
    private ?array $streakCache = null;

    /** @var array<int|string, string>|null */
    private ?array $moodCache = null;

    /** @var array<string, true>|null */
    private ?array $birthdayUuids = null;

    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
        private readonly NotificationService $notifications,
        private readonly HrmDepartmentSyncService $hrmDepartments,
        private readonly HrmEmployeeDirectory $hrmEmployees,
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

    /**
     * @param  list<int>  $userIds
     */
    public function createGroup(User $actor, string $title, array $userIds): array
    {
        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['Vui lòng nhập tên nhóm.'],
            ]);
        }

        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $actor->id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Chọn ít nhất một thành viên khác bạn.'],
            ]);
        }

        if ($ids->count() > 49) {
            throw ValidationException::withMessages([
                'user_ids' => ['Nhóm chỉ có tối đa 50 thành viên.'],
            ]);
        }

        $activeCount = User::query()->whereIn('id', $ids)->get()->filter(fn (User $user) => $user->isActive())->count();
        if ($activeCount !== $ids->count()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Có thành viên không tồn tại hoặc đã ngừng hoạt động.'],
            ]);
        }

        $conversation = $this->conversations->createGroup($actor->id, $title, $ids->all());

        $this->sendMessage($actor, $conversation->id, [
            'message' => $actor->name.' đã tạo nhóm «'.$title.'»',
        ]);

        $fresh = $this->requireConversation($conversation->id);
        $fresh->load(['members.user.department']);

        return $this->presentConversation($fresh, $actor, 0);
    }

    public function listConversations(User $actor): array
    {
        $conversations = $this->conversations->listForUser($actor->id);
        $this->primeFunContext($conversations);
        $unreadCounts = $this->messages->unreadCountsForUser($actor->id);

        $cards = $conversations
            ->map(fn (Conversation $conversation) => $this->presentConversation(
                $conversation,
                $actor,
                $unreadCounts[$conversation->id] ?? 0,
            ))
            ->all();

        usort($cards, function (array $left, array $right) {
            $pin = (int) (($right['pinned_at'] ?? null) !== null) <=> (int) (($left['pinned_at'] ?? null) !== null);
            if ($pin !== 0) {
                return $pin;
            }

            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        });

        return array_values($cards);
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
        if (! in_array($type, [Message::TYPE_TEXT, Message::TYPE_STICKER, Message::TYPE_VOICE, Message::TYPE_POLL], true)) {
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
        } elseif ($type === Message::TYPE_VOICE) {
            if ($files === []) {
                throw ValidationException::withMessages([
                    'attachments' => ['Chưa có tin nhắn thoại.'],
                ]);
            }
            $body = '';
        } elseif ($type === Message::TYPE_POLL) {
            $files = [];
            if ($body === '') {
                throw ValidationException::withMessages([
                    'message' => ['Vui lòng nhập câu hỏi bình chọn.'],
                ]);
            }
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
            'message_type' => match ($type) {
                Message::TYPE_VOICE => Message::TYPE_VOICE,
                Message::TYPE_POLL => Message::TYPE_POLL,
                default => Message::TYPE_TEXT,
            },
            'voice_duration' => $type === Message::TYPE_VOICE
                ? max(1, min(180, (int) ($input['voice_duration'] ?? 1)))
                : null,
        ]);

        $conversation = $this->requireConversation($conversationId);
        if ($type === Message::TYPE_POLL) {
            $this->storePoll($message, $body, is_array($input['poll_options'] ?? null) ? $input['poll_options'] : []);
        }
        $mentionIds = $this->storeMentions(
            $actor,
            $conversation->loadMissing('members'),
            $message,
            is_array($input['mention_ids'] ?? null) ? $input['mention_ids'] : [],
        );
        if ($conversation->disappear_seconds) {
            $message->expires_at = now()->addSeconds((int) $conversation->disappear_seconds);
            $message->save();
        }

        if ($files !== []) {
            $this->storeAttachments($message, $files);
        }
        $message = $this->messages->findForConversation($conversationId, $message->id) ?? $message;

        Conversation::where('id', $conversationId)->update(['updated_at' => now()]);

        $presented = $this->present($message);

        MessageSent::dispatch($conversationId, $presented);
        $this->notifyOthers($actor, $conversationId, $presented, $message, $mentionIds);

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
        $readIds = $this->messages->markVisibleRead($conversationId, $actor->id);
        $this->markChatNotificationsRead($actor->id, $conversationId);
        Cache::put($this->viewingKey($actor->id), $conversationId, now()->addSeconds(45));

        MessageRead::dispatch(
            $conversationId,
            $actor->id,
            now()->toIso8601String(),
            $readIds,
            $actor->name,
            $actor->avatar_url,
        );
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

    public function clearHistory(User $actor, int $conversationId): void
    {
        $this->messages->hideAllForUser($conversationId, $actor->id);
        $this->markRead($actor, $conversationId);
    }

    public function disbandGroup(User $actor, int $conversationId): void
    {
        if (! $this->isMember($actor, $conversationId)) {
            throw new AccessDeniedHttpException('Bạn không thuộc cuộc trò chuyện này.');
        }

        $conversation = $this->requireConversation($conversationId);
        if ($conversation->type !== Conversation::TYPE_GROUP) {
            throw ValidationException::withMessages([
                'conversation' => ['Chỉ giải tán được nhóm chat.'],
            ]);
        }

        if ((int) $conversation->created_by !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Chỉ chủ nhóm mới giải tán được nhóm.');
        }

        $conversation->loadMissing('members');
        $memberIds = $conversation->members
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $attachments = MessageAttachment::query()
            ->whereIn('message_id', Message::query()->where('conversation_id', $conversationId)->select('id'))
            ->get(['file_path', 'disk']);
        foreach ($attachments as $attachment) {
            if (is_string($attachment->file_path) && $attachment->file_path !== '') {
                Storage::disk($attachment->disk ?: 'public')->delete($attachment->file_path);
            }
        }

        $conversation->delete();

        foreach ($memberIds as $memberId) {
            ConversationRemoved::dispatch($memberId, $conversationId);
        }
    }

    public function broadcastTyping(User $actor, int $conversationId, bool $active = true): void
    {
        Cache::put($this->viewingKey($actor->id), $conversationId, now()->addSeconds(45));
        UserTyping::dispatch($conversationId, $actor->id, $actor->name, $active);
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

    private function upsertChatNotification(User $recipient, User $actor, int $conversationId, string $title, string $body, mixed $avatar, string $pushBody): void
    {
        $data = [
            'conversation_id' => $conversationId,
            'push_icon' => is_string($avatar) ? $avatar : null,
            'push_tag' => 'va-chat-'.$conversationId,
            'push_body' => $pushBody,
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
                'url' => '/dashboard/me/chat?chat='.$conversationId,
                'data' => [
                    'conversation_id' => $conversationId,
                ],
                'created_at' => now(),
            ])->save();

            $this->notifications->sendWebPush(
                $recipient,
                $actor,
                NotificationService::TYPE_CHAT_MESSAGE,
                $title,
                $body,
                '/dashboard/me/chat?chat='.$conversationId,
                [
                    'push_icon' => is_string($avatar) ? $avatar : null,
                    'push_tag' => 'va-chat-'.$conversationId,
                    'push_body' => $pushBody,
                ],
            );

            return;
        }

        $this->notifications->notify(
            recipient: $recipient,
            actor: $actor,
            type: NotificationService::TYPE_CHAT_MESSAGE,
            title: $title,
            body: $body,
            url: '/dashboard/me/chat?chat='.$conversationId,
            data: $data,
        );
    }

    private function notificationExcerpt(Message $message): string
    {
        if ($this->isSticker($message)) {
            return 'Đã gửi một sticker';
        }

        if ($message->message_type === Message::TYPE_VOICE) {
            return 'Đã gửi một tin nhắn thoại';
        }

        if ($message->message_type === Message::TYPE_POLL) {
            return 'Đã tạo một bình chọn';
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

    /**
     * Nội dung push điện thoại: dòng 1 = ai gửi (+ nhóm), dòng 2 = tin nhắn.
     */
    private function pushBody(User $actor, Conversation $conversation, Message $message, bool $mentioned): string
    {
        $header = $mentioned ? "{$actor->name} nhắc bạn" : (string) $actor->name;
        $groupTitle = trim((string) $conversation->title);
        if ($conversation->type === Conversation::TYPE_GROUP && $groupTitle !== '') {
            $header .= ' · '.$groupTitle;
        }

        return $header."\n".$this->pushExcerpt($message);
    }

    /** Một dòng, có emoji cho tin không phải chữ (sticker, thoại, ảnh, tệp, bình chọn). */
    private function pushExcerpt(Message $message): string
    {
        $emoji = match (true) {
            $this->isSticker($message) => '🙂',
            $message->message_type === Message::TYPE_VOICE => '🎙️',
            $message->message_type === Message::TYPE_POLL => '📊',
            trim($message->message) === '' && $message->attachments->isNotEmpty() => $message->attachments->every(fn ($file) => $file->type === 'image') ? '📷' : '📎',
            default => '',
        };

        $excerpt = $this->notificationExcerpt($message);
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt) ?? $excerpt;

        return $emoji !== '' ? "{$emoji} {$excerpt}" : $excerpt;
    }

    private function notifyOthers(User $actor, int $conversationId, array $presented, Message $message, array $mentionIds = []): void
    {
        $conversation = $this->requireConversation($conversationId);
        $conversation->loadMissing(['members.user.department']);

        foreach ($conversation->members as $member) {
            $recipient = $member->user;
            if (! $recipient || (int) $recipient->id === (int) $actor->id) {
                continue;
            }

            $unread = $this->messages->unreadCountsForUser($recipient->id)[$conversationId] ?? 1;
            $card = $this->presentConversation($conversation, $recipient, $unread);
            InboxUpdated::dispatch($recipient->id, $card, $presented);

            if ((int) Cache::get($this->viewingKey($recipient->id)) === $conversationId) {
                continue;
            }

            $mentioned = in_array((int) $recipient->id, $mentionIds, true);
            if ($this->memberIsMuted($member) && ! $mentioned) {
                continue;
            }

            $title = $mentioned
                ? "{$actor->name} đã nhắc bạn trong {$conversation->title}"
                : ($conversation->type === Conversation::TYPE_GROUP
                    ? "{$actor->name} đã gửi tin trong {$conversation->title}"
                    : "{$actor->name} đã gửi cho bạn một tin nhắn");

            $this->upsertChatNotification(
                $recipient,
                $actor,
                $conversationId,
                $title,
                $this->notificationExcerpt($message),
                $actor->avatar_url,
                $this->pushBody($actor, $conversation, $message, $mentioned),
            );
        }
    }

    private function presentConversation(Conversation $conversation, User $actor, ?int $unreadCount = null): array
    {
        $conversation->loadMissing(['members.user.department']);
        $isGroup = $conversation->type === Conversation::TYPE_GROUP;
        $other = $conversation->members->firstWhere('user_id', '!=', $actor->id);
        $latest = $this->messages->latestVisibleForUser($conversation->id, $actor->id);
        $members = $conversation->members
            ->filter(fn ($member) => $member->user)
            ->map(fn ($member) => [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'avatar_url' => $member->user->avatar_url,
                'mood' => $this->chatMoodFor($member->user),
                'birthday_today' => $this->chatBirthdayToday($member->user),
                'last_seen_at' => $member->user->last_seen_at?->toIso8601String(),
                'online' => $member->user->last_seen_at !== null && $member->user->last_seen_at->greaterThan(now()->subSeconds(90)),
            ])
            ->values()
            ->all();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $isGroup ? $conversation->title : null,
            'avatar_url' => $isGroup ? $this->groupAvatarUrl($conversation) : null,
            'member_count' => count($members),
            'members' => $isGroup ? $members : [],
            'other_user' => $isGroup ? null : $this->presentOtherUser($other),
            'other_read_at' => $isGroup ? null : $other?->last_read_at?->toIso8601String(),
            'last_message' => $latest ? [
                'id' => $latest->id,
                'message' => $this->previewText($latest),
                'message_type' => $this->publicType($latest),
                'recalled' => $latest->isRecalled(),
                'sender_id' => $latest->sender_id,
                'sender_name' => $latest->sender?->name,
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => $unreadCount ?? 0,
        ] + $this->conversationExtras($conversation, $actor);
    }

    private function previewText(Message $message): string
    {
        if ($message->isRecalled()) {
            return 'Tin nhắn đã được thu hồi';
        }

        if ($this->isSticker($message)) {
            return 'Sticker';
        }

        if ($message->message_type === Message::TYPE_VOICE) {
            return 'Tin nhắn thoại';
        }

        if ($message->message_type === Message::TYPE_POLL) {
            return 'Bình chọn';
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
            'mood' => $this->chatMoodFor($member->user),
            'birthday_today' => $this->chatBirthdayToday($member->user),
            'department' => $member->user->department?->name,
            'job_title_name' => $this->hrmDepartments->hrmJobTitleFor($member->user),
            'last_seen_at' => $member->user->last_seen_at?->toIso8601String(),
            'online' => $member->user->last_seen_at !== null && $member->user->last_seen_at->greaterThan(now()->subSeconds(90)),
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
        ] + $this->messageExtras($message);
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
            $path = $file->store('chat/'.$message->id, 'local');
            $mime = $file->getMimeType();
            $kind = 'file';
            if (in_array($mime, self::IMAGE_MIMES, true)) {
                $kind = 'image';
            } elseif ($message->message_type === Message::TYPE_VOICE || (is_string($mime) && str_starts_with($mime, 'audio/'))) {
                $kind = 'audio';
            }
            $message->attachments()->create([
                'file_path' => $path,
                'disk' => 'local',
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize() ?: 0,
                'mime_type' => $mime,
                'type' => $kind,
            ]);
        }
    }

    private function deleteAttachments(Message $message): void
    {
        $message->loadMissing('attachments');
        foreach ($message->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk($attachment->disk ?: 'public')->delete($attachment->file_path);
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
            'url' => $this->attachmentUrl($file),
        ])->values()->all();
    }

    private function groupAvatarUrl(Conversation $conversation): ?string
    {
        if (! $conversation->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($conversation->avatar_path);
    }

    private function attachmentUrl(MessageAttachment $file): string
    {
        $disk = $file->disk ?: 'public';
        if ($disk === 'public') {
            return Storage::disk('public')->url($file->file_path);
        }

        return url('/api/chat/attachments/'.$file->id);
    }

    private function postNotice(User $actor, int $conversationId, string $text): void
    {
        $message = $this->messages->create([
            'conversation_id' => $conversationId,
            'sender_id' => $actor->id,
            'message' => $text,
            'message_type' => Message::TYPE_SYSTEM,
        ]);
        Conversation::where('id', $conversationId)->update(['updated_at' => now()]);
        MessageSent::dispatch($conversationId, $this->present($message));
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

    public function setMood(User $actor, ?string $mood): array
    {
        if ($mood !== null && ! in_array($mood, self::MOODS, true)) {
            throw ValidationException::withMessages([
                'mood' => ['Tâm trạng không hợp lệ.'],
            ]);
        }

        if ($mood === null) {
            ChatUserMood::query()->where('user_id', $actor->id)->delete();
        } else {
            ChatUserMood::query()->updateOrCreate(
                ['user_id' => $actor->id],
                ['mood' => $mood],
            );
        }

        $this->moodCache = null;
        $conversationIds = ConversationMember::query()
            ->where('user_id', $actor->id)
            ->orderByDesc('updated_at')
            ->limit(30)
            ->pluck('conversation_id');

        foreach ($conversationIds as $conversationId) {
            $this->refreshMemberInboxes((int) $conversationId);
        }

        return ['mood' => $mood];
    }

    public function setGroupAccent(User $actor, int $conversationId, ?string $accent): array
    {
        if ($accent !== null && ! in_array($accent, self::ACCENTS, true)) {
            throw ValidationException::withMessages([
                'accent' => ['Màu nhóm không hợp lệ.'],
            ]);
        }

        $conversation = $this->requireOwnedGroup($actor, $conversationId);
        $conversation->update(['accent' => $accent]);
        $this->refreshMemberInboxes($conversationId);

        return $this->presentConversation($this->requireConversation($conversationId), $actor);
    }

    /**
     * @param  iterable<int, Conversation>  $conversations
     */
    private function primeFunContext(iterable $conversations): void
    {
        $this->streakCache = $this->computeStreaks($conversations);
        $userIds = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation->members as $member) {
                if ($member->user_id) {
                    $userIds[] = (int) $member->user_id;
                }
            }
        }

        $this->moodCache = $userIds === []
            ? []
            : ChatUserMood::query()->whereIn('user_id', array_values(array_unique($userIds)))->pluck('mood', 'user_id')->all();
    }

    protected function chatMoodFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($this->moodCache === null) {
            $mood = ChatUserMood::query()->where('user_id', $user->id)->value('mood');

            return is_string($mood) && in_array($mood, self::MOODS, true) ? $mood : null;
        }

        $mood = $this->moodCache[$user->id] ?? $this->moodCache[(string) $user->id] ?? null;

        return is_string($mood) && in_array($mood, self::MOODS, true) ? $mood : null;
    }

    protected function chatBirthdayToday(?User $user): bool
    {
        $uuid = trim((string) ($user->hrm_employee_uuid ?? ''));
        if ($uuid === '') {
            return false;
        }

        return isset($this->birthdayUuidSet()[$uuid]);
    }

    protected function chatStreak(Conversation $conversation): int
    {
        if ($conversation->type !== Conversation::TYPE_PRIVATE) {
            return 0;
        }

        if ($this->streakCache !== null) {
            return (int) ($this->streakCache[$conversation->id] ?? 0);
        }

        return (int) ($this->computeStreaks([$conversation])[$conversation->id] ?? 0);
    }

    /**
     * @return array<string, true>
     */
    private function birthdayUuidSet(): array
    {
        if ($this->birthdayUuids !== null) {
            return $this->birthdayUuids;
        }

        $cacheKey = 'chat.birthday-uuids.'.now()->toDateString();
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            /** @var array<string, true> $cached */
            $this->birthdayUuids = $cached;

            return $cached;
        }

        $set = [];
        try {
            if (HrmEmployeeDirectory::isConfigured()) {
                $today = now()->toDateString();
                foreach ($this->hrmEmployees->upcomingBirthdays(1) as $row) {
                    $uuid = trim((string) ($row['hrm_employee_uuid'] ?? ''));
                    if ($uuid !== '' && ($row['next_birthday'] ?? '') === $today) {
                        $set[$uuid] = true;
                    }
                }
            }
        } catch (\Throwable) {
            $set = [];
        }

        Cache::put($cacheKey, $set, now()->addMinutes(15));
        $this->birthdayUuids = $set;

        return $set;
    }

    /**
     * Ngày tính khi cả hai người đều đã gửi tin. Hôm nay chưa đủ thì nối tiếp từ hôm qua.
     *
     * @param  iterable<int, Conversation>  $conversations
     * @return array<int, int>
     */
    private function computeStreaks(iterable $conversations): array
    {
        $pairs = [];
        foreach ($conversations as $conversation) {
            if ($conversation->type !== Conversation::TYPE_PRIVATE) {
                continue;
            }
            $memberIds = $conversation->members
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            if ($memberIds->count() !== 2) {
                continue;
            }
            $pairs[$conversation->id] = $memberIds->all();
        }

        if ($pairs === []) {
            return [];
        }

        $rows = Message::query()
            ->whereIn('conversation_id', array_keys($pairs))
            ->whereNull('recalled_at')
            ->where('message_type', '!=', Message::TYPE_SYSTEM)
            ->where('created_at', '>=', now()->subDays(21)->startOfDay())
            ->get(['conversation_id', 'sender_id', 'created_at']);

        $byDay = [];
        foreach ($rows as $row) {
            $day = $row->created_at?->toDateString();
            if (! is_string($day) || $day === '') {
                continue;
            }
            $byDay[$row->conversation_id][$day][(int) $row->sender_id] = true;
        }

        $streaks = [];
        foreach ($pairs as $conversationId => $memberIds) {
            $days = $byDay[$conversationId] ?? [];
            $cursor = now()->startOfDay();
            if (! $this->dayHasBoth($days[$cursor->toDateString()] ?? [], $memberIds)) {
                $cursor->subDay();
            }
            $streak = 0;
            while ($this->dayHasBoth($days[$cursor->toDateString()] ?? [], $memberIds)) {
                $streak++;
                $cursor->subDay();
            }
            $streaks[$conversationId] = $streak;
        }

        return $streaks;
    }

    /**
     * @param  array<int, true>  $senders
     * @param  list<int>  $memberIds
     */
    private function dayHasBoth(array $senders, array $memberIds): bool
    {
        foreach ($memberIds as $memberId) {
            if (empty($senders[$memberId])) {
                return false;
            }
        }

        return $memberIds !== [];
    }
}
