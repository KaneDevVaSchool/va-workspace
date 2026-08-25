<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\App\Repositories\Contracts\UserNotificationRepositoryInterface;

class UserNotificationRepository implements UserNotificationRepositoryInterface
{
    public function create(array $data): UserNotification
    {
        return UserNotification::query()->create($data);
    }

    public function findForUser(int $id, int $userId): ?UserNotification
    {
        return UserNotification::query()
            ->with('actor')
            ->where('user_id', $userId)
            ->find($id);
    }

    public function paginateForUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return UserNotification::query()
            ->with('actor')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function unreadCount(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification;
    }

    public function markAllRead(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
