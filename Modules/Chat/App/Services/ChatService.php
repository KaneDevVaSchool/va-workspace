<?php

namespace Modules\Chat\App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Chat\App\Events\MessageRead;
use Modules\Chat\App\Events\MessageSent;
use Modules\Chat\App\Models\Conversation;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Repositories\Contracts\ConversationRepositoryInterface;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;
use Modules\Identity\App\Services\NotificationService;

class ChatService
{
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
        $messages = $this->messages->paginateBefore($conversationId, $beforeId, 30);

        return [
            'messages' => $messages->map(fn (Message $message) => $this->present($message))->values()->all(),
            'has_more' => $messages->count() === 30,
        ];
    }

    public function sendMessage(User $actor, int $conversationId, string $body): array
    {
        $message = $this->messages->create([
            'conversation_id' => $conversationId,
            'sender_id' => $actor->id,
            'message' => $body,
            'message_type' => Message::TYPE_TEXT,
        ]);

        Conversation::where('id', $conversationId)->update(['updated_at' => now()]);

        $presented = $this->present($message);

        MessageSent::dispatch($conversationId, $presented);

        $recipient = $this->conversations->otherMember($conversationId, $actor->id);

        if ($recipient) {
            $this->notifications->notify(
                recipient: $recipient->user,
                actor: $actor,
                type: NotificationService::TYPE_CHAT_MESSAGE,
                title: "{$actor->name} đã gửi cho bạn một tin nhắn",
                body: Str::limit($body, 140),
                url: '/?chat='.$conversationId,
                data: ['conversation_id' => $conversationId, 'message_id' => $message->id],
            );
        }

        return ['message' => $presented];
    }

    public function markRead(User $actor, int $conversationId): void
    {
        $this->conversations->markRead($conversationId, $actor->id);

        MessageRead::dispatch($conversationId, $actor->id, now()->toIso8601String());
    }

    public function unreadSummary(User $actor): array
    {
        $counts = $this->messages->unreadCountsForUser($actor->id);

        return ['unread_total' => array_sum($counts)];
    }

    private function presentConversation(Conversation $conversation, User $actor, ?int $unreadCount = null): array
    {
        $other = $conversation->relationLoaded('members')
            ? $conversation->members->firstWhere('user_id', '!=', $actor->id)
            : $this->conversations->otherMember($conversation->id, $actor->id);

        $latest = $conversation->relationLoaded('latestMessage')
            ? $conversation->latestMessage
            : $this->messages->latestForConversation($conversation->id);

        return [
            'id' => $conversation->id,
            'other_user' => $this->presentOtherUser($other),
            'last_message' => $latest ? [
                'message' => $latest->message,
                'sender_id' => $latest->sender_id,
                'created_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => $unreadCount ?? 0,
        ];
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
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'avatar_url' => $message->sender->avatar_url,
            ],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
