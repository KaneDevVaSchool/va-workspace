<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\App\Repositories\Contracts\UserNotificationRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Throwable;

class NotificationService
{
    public const TYPE_MENTION_POST = 'mention_post';

    public const TYPE_MENTION_COMMENT = 'mention_comment';

    public const TYPE_GROUP_JOIN_REQUEST = 'group_join_request';

    public const TYPE_GROUP_JOIN_APPROVED = 'group_join_approved';

    public const TYPE_GROUP_JOIN_REJECTED = 'group_join_rejected';

    public const TYPE_GROUP_INVITE = 'group_invite';

    public function __construct(
        private readonly UserNotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly WebPushService $webPush,
    ) {}

    /**
     * @param  list<int>  $recipientIds
     * @param  array<string, mixed>  $data
     */
    public function notifyUsers(
        array $recipientIds,
        User $actor,
        string $type,
        string $title,
        ?string $body,
        ?string $url,
        array $data = [],
    ): void {
        $ids = collect($recipientIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $actor->id || $id < 1)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        foreach ($this->users->findActiveByIds($ids) as $recipient) {
            $this->notify($recipient, $actor, $type, $title, $body, $url, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        User $recipient,
        User $actor,
        string $type,
        string $title,
        ?string $body,
        ?string $url,
        array $data = [],
    ): ?UserNotification {
        if ((int) $recipient->id === (int) $actor->id || ! $recipient->isActive()) {
            return null;
        }

        $notification = $this->notifications->create([
            'user_id' => $recipient->id,
            'actor_id' => $actor->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data,
        ]);

        try {
            $this->webPush->sendToUser($recipient->id, [
                'title' => $title,
                'body' => $body ?? '',
                'url' => $url ?? '/social',
                'tag' => 'va-n-'.$notification->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('Web push failed.', [
                'notification_id' => $notification->id,
                'user_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->notifications->paginateForUser($user->id, $perPage);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCount($user->id);
    }

    public function markRead(User $user, int $id): ?UserNotification
    {
        $notification = $this->notifications->findForUser($id, $user->id);
        if ($notification === null) {
            return null;
        }

        return $this->notifications->markRead($notification);
    }

    public function markAllRead(User $user): int
    {
        return $this->notifications->markAllRead($user->id);
    }

    public function present(UserNotification $notification): array
    {
        $actor = $notification->actor;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'data' => $notification->data ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'actor' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name,
                'avatar_url' => $actor->avatar_url,
            ] : null,
        ];
    }
}
