<?php

namespace Modules\Chat\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Chat\App\Models\Message;

interface MessageRepositoryInterface
{
    public function create(array $data): Message;

    public function findForConversation(int $conversationId, int $messageId): ?Message;

    public function save(Message $message): Message;

    public function hideForUser(int $messageId, int $userId): void;

    /**
     * Tin nhắn mới nhất trước, phân trang theo con trỏ id (before_id).
     * Bỏ tin người xem đã xoá ở phía mình.
     *
     * @return Collection<int, Message>
     */
    public function paginateBefore(int $conversationId, int $viewerId, ?int $beforeId, int $perPage = 30): Collection;

    /**
     * @return array<int, int> conversation_id => số tin chưa đọc
     */
    public function unreadCountsForUser(int $userId): array;

    public function latestVisibleForUser(int $conversationId, int $viewerId): ?Message;
}
