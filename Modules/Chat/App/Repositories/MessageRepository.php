<?php

namespace Modules\Chat\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Models\MessageHide;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): Message
    {
        $message = Message::create($data);

        return $message->loadMissing(['sender', 'replyTo.sender', 'replyTo.attachments', 'attachments']);
    }

    public function findForConversation(int $conversationId, int $messageId): ?Message
    {
        return Message::query()
            ->with(['sender', 'replyTo.sender', 'replyTo.attachments', 'attachments'])
            ->where('conversation_id', $conversationId)
            ->whereKey($messageId)
            ->first();
    }

    public function save(Message $message): Message
    {
        $message->save();

        return $message->load(['sender', 'replyTo.sender', 'replyTo.attachments', 'attachments']);
    }

    public function hideForUser(int $messageId, int $userId): void
    {
        MessageHide::query()->firstOrCreate([
            'message_id' => $messageId,
            'user_id' => $userId,
        ]);
    }

    public function paginateBefore(int $conversationId, int $viewerId, ?int $beforeId, int $perPage = 30): Collection
    {
        $query = Message::query()
            ->with(['sender', 'replyTo.sender', 'replyTo.attachments', 'attachments'])
            ->where('conversation_id', $conversationId)
            ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $viewerId))
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($perPage)->get();
    }

    public function unreadCountsForUser(int $userId): array
    {
        $memberships = ConversationMember::query()
            ->where('user_id', $userId)
            ->get(['conversation_id', 'last_read_at']);

        if ($memberships->isEmpty()) {
            return [];
        }

        $counts = [];

        foreach ($memberships as $membership) {
            $query = Message::query()
                ->where('conversation_id', $membership->conversation_id)
                ->where('sender_id', '!=', $userId)
                ->whereNull('recalled_at')
                ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $userId));

            if ($membership->last_read_at !== null) {
                $query->where('created_at', '>', $membership->last_read_at);
            }

            $count = $query->count();

            if ($count > 0) {
                $counts[$membership->conversation_id] = $count;
            }
        }

        return $counts;
    }

    public function latestVisibleForUser(int $conversationId, int $viewerId): ?Message
    {
        return Message::query()
            ->with('attachments')
            ->where('conversation_id', $conversationId)
            ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $viewerId))
            ->orderByDesc('id')
            ->first();
    }
}
