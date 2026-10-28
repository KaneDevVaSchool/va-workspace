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

    public const TYPE_SOCIAL_COMMENT = 'social_comment';

    public const TYPE_SOCIAL_POST_PENDING_REVIEW = 'social_post_pending_review';

    public const TYPE_SOCIAL_POST_APPROVED = 'social_post_approved';

    public const TYPE_SOCIAL_POST_REJECTED = 'social_post_rejected';

    public const TYPE_LIKE_POST = 'like_post';

    public const TYPE_LIKE_COMMENT = 'like_comment';

    public const TYPE_SHARE_POST = 'share_post';

    public const TYPE_GROUP_POST = 'group_post';

    public const TYPE_GROUP_JOIN_REQUEST = 'group_join_request';

    public const TYPE_GROUP_JOIN_APPROVED = 'group_join_approved';

    public const TYPE_GROUP_JOIN_REJECTED = 'group_join_rejected';

    public const TYPE_GROUP_INVITE = 'group_invite';

    /** Module Project — mở rộng entity Task (Nhóm E/G), xem plan mở rộng Task PR5/PR6. */
    public const TYPE_TASK_WORKLOG_ADDED = 'task_worklog_added';

    public const TYPE_TASK_SCORED = 'task_scored';

    public const TYPE_TASK_DELEGATED = 'task_delegated';

    public const TYPE_TASK_ASSIGNED = 'task_assigned';

    public const TYPE_TASK_DUE_SOON = 'task_due_soon';

    public const TYPE_DISCUSSION_COMMENT = 'discussion_comment';

    public const TYPE_FEATURE_REQUEST_CREATED = 'feature_request_created';

    public const TYPE_FEATURE_REQUEST_APPROVED = 'feature_request_approved';

    public const TYPE_FEATURE_REQUEST_REJECTED = 'feature_request_rejected';

    public const TYPE_FEATURE_REQUEST_DONE = 'feature_request_done';

    public const TYPE_CHAT_MESSAGE = 'chat_message';

    /** Bảng chấm công HRM chờ người nhận duyệt (webhook attendance.timesheet.status_changed). */
    public const TYPE_HRM_TIMESHEET_PENDING = 'hrm_timesheet_pending';

    /** Bảng chấm công HRM bị trả về — Admin đơn vị cần sửa và gửi lại. */
    public const TYPE_HRM_TIMESHEET_REJECTED = 'hrm_timesheet_rejected';

    /** Bảng chấm công HRM đã duyệt cuối — đóng, khóa chỉnh sửa. */
    public const TYPE_HRM_TIMESHEET_FINALIZED = 'hrm_timesheet_finalized';

    public function __construct(
        private readonly UserNotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly WebPushService $webPush,
        private readonly WebPushPayloadBuilder $pushPayloadBuilder,
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

        return $this->deliver($recipient, $actor, $type, $title, $body, $url, $data);
    }

    /**
     * Thông báo hệ thống, không có người gửi (actor_id null) — vd. sự kiện từ VA-HRM qua webhook.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifySystem(
        User $recipient,
        string $type,
        string $title,
        ?string $body,
        ?string $url,
        array $data = [],
    ): ?UserNotification {
        if (! $recipient->isActive()) {
            return null;
        }

        return $this->deliver($recipient, null, $type, $title, $body, $url, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deliver(
        User $recipient,
        ?User $actor,
        string $type,
        string $title,
        ?string $body,
        ?string $url,
        array $data,
    ): UserNotification {
        $pushIcon = is_string($data['push_icon'] ?? null) ? $data['push_icon'] : null;
        $pushTag = is_string($data['push_tag'] ?? null) ? $data['push_tag'] : null;
        $pushImage = is_string($data['push_image'] ?? null) ? $data['push_image'] : null;
        $pushBody = is_string($data['push_body'] ?? null) ? $data['push_body'] : null;
        unset($data['push_icon'], $data['push_tag'], $data['push_image'], $data['push_body']);

        $notification = $this->notifications->create([
            'user_id' => $recipient->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'data' => $data,
        ]);

        $pushExtra = $pushBody !== null && trim($pushBody) !== '' ? ['push_body' => trim($pushBody)] : [];

        $this->dispatchWebPush(
            $recipient->id,
            $type,
            $actor,
            $title,
            $body,
            $url,
            $pushIcon,
            $pushTag,
            $pushImage,
            $notification->id,
            $pushExtra,
        );

        return $notification;
    }

    /**
     * Gửi lại web push (vd. gộp tin nhắn chat) mà không tạo bản ghi chuông mới.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendWebPush(
        User $recipient,
        ?User $actor,
        string $type,
        string $title,
        ?string $body,
        ?string $url,
        array $data = [],
    ): void {
        $pushIcon = is_string($data['push_icon'] ?? null) ? $data['push_icon'] : null;
        $pushTag = is_string($data['push_tag'] ?? null) ? $data['push_tag'] : null;
        $pushImage = is_string($data['push_image'] ?? null) ? $data['push_image'] : null;
        $pushBody = is_string($data['push_body'] ?? null) ? $data['push_body'] : null;

        $extra = $pushBody !== null && $pushBody !== '' ? ['push_body' => $pushBody] : [];

        $this->dispatchWebPush(
            (int) $recipient->id,
            $type,
            $actor,
            $title,
            $body,
            $url,
            $pushIcon,
            $pushTag,
            $pushImage,
            null,
            $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extraPushData
     */
    private function dispatchWebPush(
        int $userId,
        string $type,
        ?User $actor,
        string $title,
        ?string $body,
        ?string $url,
        ?string $pushIcon,
        ?string $pushTag,
        ?string $pushImage,
        ?int $notificationId,
        array $extraPushData = [],
    ): void {
        $formatted = $this->pushPayloadBuilder->forPhone(
            $type,
            $this->pushPayloadBuilder->build(
                $type,
                $actor,
                $title,
                $body,
                $extraPushData,
            ),
        );

        $pushPayload = [
            'title' => $formatted['title'],
            'body' => $formatted['body'],
            'url' => $url ?? '/dashboard/me/feed',
            'tag' => $pushTag ?? ('va-n-'.($notificationId ?? uniqid('', true))),
            'timestamp' => time() * 1000,
        ];

        if ($pushIcon !== null && $pushIcon !== '') {
            $pushPayload['icon'] = $pushIcon;
        }
        if ($pushImage !== null && $pushImage !== '') {
            $pushPayload['image'] = $pushImage;
        }

        try {
            $this->webPush->sendToUser($userId, $pushPayload);
        } catch (Throwable $e) {
            Log::warning('Web push failed.', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->notifications->paginateForUser($user->id, $perPage);
    }

    public function unreadCount(User $user): int
    {
        return $this->notifications->unreadCount($user->id);
    }

    /** @return array<string, mixed>|null */
    public function latestUnreadPresented(User $user): ?array
    {
        $notification = UserNotification::query()
            ->with('actor')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $notification ? $this->present($notification) : null;
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

        $pushLine = $this->pushPayloadBuilder->build(
            (string) $notification->type,
            $actor,
            (string) $notification->title,
            $notification->body,
        )['body'];

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'push_line' => $pushLine,
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
