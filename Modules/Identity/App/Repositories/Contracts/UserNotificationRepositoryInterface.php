<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\UserNotification;

interface UserNotificationRepositoryInterface
{
    public function create(array $data): UserNotification;

    public function findForUser(int $id, int $userId): ?UserNotification;

    public function paginateForUser(int $userId, int $perPage = 20): LengthAwarePaginator;

    public function unreadCount(int $userId): int;

    public function markRead(UserNotification $notification): UserNotification;

    public function markAllRead(int $userId): int;
}
