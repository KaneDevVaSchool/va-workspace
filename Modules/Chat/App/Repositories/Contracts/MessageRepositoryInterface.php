<?php

namespace Modules\Chat\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Chat\App\Models\Message;

interface MessageRepositoryInterface
{
    public function create(array $data): Message;

    /**
     * Tin nhắn mới nhất trước, phân trang theo con trỏ id (before_id).
     *
     * @return Collection<int, Message>
     */
    public function paginateBefore(int $conversationId, ?int $beforeId, int $perPage = 30): Collection;

    /**
     * @return array<int, int> conversation_id => số tin chưa đọc
     */
    public function unreadCountsForUser(int $userId): array;

    public function latestForConversation(int $conversationId): ?Message;
}
