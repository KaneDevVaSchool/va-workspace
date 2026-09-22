<?php

namespace Modules\Chat\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Chat\App\Models\Conversation;
use Modules\Chat\App\Models\ConversationMember;

interface ConversationRepositoryInterface
{
    public function findPrivateBetween(int $userIdA, int $userIdB): ?Conversation;

    public function createPrivate(int $userIdA, int $userIdB): Conversation;

    public function findOrCreatePrivate(int $userIdA, int $userIdB): Conversation;

    public function find(int $id): ?Conversation;

    public function isMember(int $conversationId, int $userId): bool;

    /**
     * Cuộc trò chuyện của user, kèm last message + người còn lại, mới nhất trước.
     *
     * @return Collection<int, Conversation>
     */
    public function listForUser(int $userId): Collection;

    public function markRead(int $conversationId, int $userId): void;

    public function otherMember(int $conversationId, int $userId): ?ConversationMember;
}
