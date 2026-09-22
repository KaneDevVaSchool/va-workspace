<?php

namespace Modules\Chat\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): Message
    {
        $message = Message::create($data);

        return $message->loadMissing('sender');
    }

    public function paginateBefore(int $conversationId, ?int $beforeId, int $perPage = 30): Collection
    {
        $query = Message::query()
            ->with('sender')
            ->where('conversation_id', $conversationId)
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
                ->where('sender_id', '!=', $userId);

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

    public function latestForConversation(int $conversationId): ?Message
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->first();
    }
}
