<?php

namespace Modules\Identity\App\Services;

use App\Models\User;

/**
 * Gói payload web push: thương hiệu "VA Workspace" + một dòng nội dung có nghĩa.
 */
final class WebPushPayloadBuilder
{
    public const BRAND_TITLE = 'VA Workspace';

    private const BODY_MAX = 240;

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, body: string}
     */
    public function build(
        string $type,
        ?User $actor,
        string $inboxTitle,
        ?string $inboxBody,
        array $data = [],
    ): array {
        $override = is_string($data['push_body'] ?? null) ? trim($data['push_body']) : '';
        if ($override !== '') {
            return [
                'title' => self::BRAND_TITLE,
                'body' => $this->trimBody($override),
            ];
        }

        $actorName = $this->actorName($actor);
        $detail = trim((string) ($inboxBody ?? ''));
        $title = trim($inboxTitle);

        $line = match ($type) {
            NotificationService::TYPE_CHAT_MESSAGE => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Tin nhắn mới'),
            NotificationService::TYPE_SOCIAL_COMMENT,
            NotificationService::TYPE_MENTION_COMMENT => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Bình luận mới'),
            NotificationService::TYPE_MENTION_POST => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Nhắc bạn trong bài viết'),
            NotificationService::TYPE_LIKE_POST => $actorName !== '' ? "{$actorName} thích bài viết của bạn" : 'Có lượt thích mới trên bài viết',
            NotificationService::TYPE_LIKE_COMMENT => $actorName !== '' ? "{$actorName} thích bình luận của bạn" : 'Có lượt thích mới trên bình luận',
            NotificationService::TYPE_SHARE_POST => $actorName !== '' ? "{$actorName} chia sẻ bài viết của bạn" : 'Bài viết được chia sẻ',
            NotificationService::TYPE_GROUP_POST => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Bài viết mới trong nhóm'),
            NotificationService::TYPE_GROUP_JOIN_REQUEST => $actorName !== '' ? "{$actorName} xin tham gia nhóm" : 'Có yêu cầu tham gia nhóm',
            NotificationService::TYPE_GROUP_JOIN_APPROVED => 'Yêu cầu tham gia nhóm đã được duyệt',
            NotificationService::TYPE_GROUP_JOIN_REJECTED => 'Yêu cầu tham gia nhóm không được duyệt',
            NotificationService::TYPE_GROUP_INVITE => $actorName !== '' ? "{$actorName} mời bạn vào nhóm" : 'Lời mời tham gia nhóm',
            NotificationService::TYPE_TASK_ASSIGNED => $this->taskLine($actorName, 'Giao việc', $detail, $title),
            NotificationService::TYPE_TASK_DELEGATED => $this->taskLine($actorName, 'Chuyển giao', $detail, $title),
            NotificationService::TYPE_TASK_DUE_SOON => $this->taskLine(null, 'Sắp đến hạn', $detail, $title),
            NotificationService::TYPE_TASK_WORKLOG_ADDED => $this->taskLine($actorName, 'Cập nhật tiến độ', $detail, $title),
            NotificationService::TYPE_TASK_SCORED => $this->taskLine($actorName, 'Chấm điểm công việc', $detail, $title),
            NotificationService::TYPE_DISCUSSION_COMMENT => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Bình luận thảo luận mới'),
            NotificationService::TYPE_FEATURE_REQUEST_CREATED => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Đề xuất tính năng mới'),
            NotificationService::TYPE_FEATURE_REQUEST_APPROVED => 'Đề xuất tính năng đã được duyệt',
            NotificationService::TYPE_FEATURE_REQUEST_REJECTED => 'Đề xuất tính năng không được duyệt',
            NotificationService::TYPE_FEATURE_REQUEST_DONE => 'Đề xuất tính năng đã hoàn thành',
            NotificationService::TYPE_SOCIAL_POST_PENDING_REVIEW => $this->actorMessage($actorName, $detail !== '' ? $detail : 'Bài viết chờ duyệt'),
            NotificationService::TYPE_SOCIAL_POST_APPROVED => 'Bài viết đã được duyệt',
            NotificationService::TYPE_SOCIAL_POST_REJECTED => $detail !== '' ? "Bài viết bị từ chối · {$detail}" : 'Bài viết không được duyệt',
            NotificationService::TYPE_HRM_TIMESHEET_PENDING => $this->systemLine($title, $detail),
            NotificationService::TYPE_HRM_TIMESHEET_REJECTED => $this->systemLine($title, $detail),
            NotificationService::TYPE_HRM_TIMESHEET_FINALIZED => $this->systemLine($title, $detail),
            default => $this->fallbackLine($actorName, $title, $detail),
        };

        return [
            'title' => self::BRAND_TITLE,
            'body' => $this->trimBody($line),
        ];
    }

    private function actorName(?User $actor): string
    {
        if ($actor === null) {
            return '';
        }

        return trim((string) $actor->name);
    }

    private function actorMessage(string $actorName, string $message): string
    {
        if ($actorName !== '' && $message !== '') {
            return "{$actorName}: {$message}";
        }

        return $actorName !== '' ? $actorName : $message;
    }

    private function taskLine(?string $actorName, string $label, string $detail, string $inboxTitle): string
    {
        $subject = $detail !== '' ? $detail : $this->stripGenericInboxTitle($inboxTitle);
        if ($subject === '') {
            $subject = $label;
        }

        if ($actorName !== null && $actorName !== '') {
            return "{$actorName} · {$label} · {$subject}";
        }

        return "{$label} · {$subject}";
    }

    private function systemLine(string $title, string $detail): string
    {
        $title = trim($title);
        $detail = trim($detail);
        if ($title !== '' && $detail !== '' && ! str_contains($detail, $title)) {
            return "{$title} · {$detail}";
        }

        return $detail !== '' ? $detail : $title;
    }

    private function fallbackLine(string $actorName, string $title, string $detail): string
    {
        if ($detail !== '') {
            return $this->actorMessage($actorName, $detail);
        }

        return $actorName !== '' && $title !== '' ? "{$actorName} · {$title}" : ($title !== '' ? $title : $actorName);
    }

    private function stripGenericInboxTitle(string $title): string
    {
        $t = trim($title);
        if ($t === '') {
            return '';
        }

        $generic = [
            'Bạn được giao công việc mới',
            'Bạn được chuyển giao 1 công việc',
            'Công việc sắp đến hạn',
        ];
        if (in_array($t, $generic, true) || preg_match('/^Bạn được chuyển giao \d+ công việc$/u', $t)) {
            return '';
        }

        return $t;
    }

    private function trimBody(string $line): string
    {
        $line = preg_replace('/\s+/u', ' ', trim($line)) ?? trim($line);
        if ($line === '') {
            return 'Có thông báo mới';
        }

        return mb_strimwidth($line, 0, self::BODY_MAX, '…');
    }
}
