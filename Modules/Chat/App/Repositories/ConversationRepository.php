<?php

namespace Modules\Chat\App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Chat\App\Models\Conversation;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Repositories\Contracts\ConversationRepositoryInterface;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function findPrivateBetween(int $userIdA, int $userIdB): ?Conversation
    {
        return Conversation::query()
            ->where('type', Conversation::TYPE_PRIVATE)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userIdA))
            ->whereHas('members', fn ($q) => $q->where('user_id', $userIdB))
            ->whereDoesntHave('members', fn ($q) => $q->whereNotIn('user_id', [$userIdA, $userIdB]))
            ->first();
    }

    public function createPrivate(int $userIdA, int $userIdB): Conversation
    {
        return DB::transaction(function () use ($userIdA, $userIdB) {
            $conversation = Conversation::create(['type' => Conversation::TYPE_PRIVATE]);

            $conversation->members()->createMany([
                ['user_id' => $userIdA, 'joined_at' => now()],
                ['user_id' => $userIdB, 'joined_at' => now()],
            ]);

            return $conversation;
        });
    }

    public function findOrCreatePrivate(int $userIdA, int $userIdB): Conversation
    {
        return $this->findPrivateBetween($userIdA, $userIdB) ?? $this->createPrivate($userIdA, $userIdB);
    }

    public function find(int $id): ?Conversation
    {
        return Conversation::find($id);
    }

    public function isMember(int $conversationId, int $userId): bool
    {
        return ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function listForUser(int $userId): Collection
    {
        return Conversation::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->with(['members.user.department', 'latestMessage.sender'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function markRead(int $conversationId, int $userId): void
    {
        ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    public function otherMember(int $conversationId, int $userId): ?ConversationMember
    {
        return ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', '!=', $userId)
            ->with('user.department')
            ->first();
    }
}
