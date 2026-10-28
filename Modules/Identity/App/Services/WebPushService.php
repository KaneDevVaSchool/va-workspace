<?php

namespace Modules\Identity\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Jobs\SendWebPushJob;
use Modules\Identity\App\Models\PushSubscription;
use Modules\Identity\App\Repositories\Contracts\PushSubscriptionRepositoryInterface;
use Throwable;

class WebPushService
{
    /** Số lần liên tiếp bị từ chối xác thực (401/403) trước khi xoá subscription. */
    public const MAX_AUTH_FAILURES = 5;

    /** Tin chat mất giá trị rất nhanh: quá 1 giờ chưa tới máy thì bỏ. */
    private const CHAT_TTL_SECONDS = 3600;

    private const DEFAULT_TTL_SECONDS = 86400;

    /** Timeout HTTP tới push service (mặc định thư viện là 30s — quá lâu khi gửi trong request). */
    private const HTTP_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly PushSubscriptionRepositoryInterface $subscriptions,
    ) {}

    public function publicKey(): ?string
    {
        $key = trim((string) config('services.webpush.public_key', ''));

        return $key !== '' ? $key : null;
    }

    public function isConfigured(): bool
    {
        return $this->publicKey() !== null
            && (string) config('services.webpush.private_key', '') !== '';
    }

    /**
     * Gửi push tới mọi trình duyệt của user. Bật `services.webpush.queue` để đẩy qua queue
     * (request gửi tin/thông báo không phải chờ push service).
     *
     * @param  array{title: string, body: string, url: string, tag?: string, icon?: string, image?: string, timestamp?: int}  $payload
     */
    public function dispatchToUser(int $userId, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        if ((bool) config('services.webpush.queue', false)) {
            SendWebPushJob::dispatch($userId, $payload)->afterCommit();

            return;
        }

        $this->sendToUser($userId, $payload);
    }

    /**
     * @param  array{title: string, body: string, url: string, tag?: string, icon?: string, image?: string, timestamp?: int}  $payload
     */
    public function sendToUser(int $userId, array $payload): void
    {
        if (! $this->isConfigured() || ! class_exists(\Minishlink\WebPush\WebPush::class)) {
            return;
        }

        $subscriptions = $this->subscriptions->forUser($userId);
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = $this->client();
        if ($webPush === null) {
            return;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $options = $this->deliveryOptions($payload);

        foreach ($subscriptions as $row) {
            try {
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $row->endpoint,
                        'publicKey' => $row->public_key,
                        'authToken' => $row->auth_token,
                        'contentEncoding' => $row->content_encoding ?: 'aes128gcm',
                    ]),
                    $json ?: '{}',
                    $options,
                );
            } catch (Throwable $e) {
                Log::warning('Invalid push subscription.', [
                    'subscription_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
                $this->subscriptions->delete($row);
            }
        }

        $byEndpoint = $subscriptions->keyBy('endpoint');

        foreach ($webPush->flush() as $report) {
            /** @var PushSubscription|null $row */
            $row = $byEndpoint->get($report->getEndpoint());

            if ($report->isSuccess()) {
                if ($row !== null && $row->failed_count > 0) {
                    $this->subscriptions->resetFailures($row);
                }

                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $this->subscriptions->deleteByEndpoint($userId, $report->getEndpoint());

                continue;
            }

            $status = $report->getResponse()?->getStatusCode();

            // 401/403: khoá VAPID bị từ chối (đổi khoá, hoặc dịch vụ push chặn). Lỗi lặp lại nhiều
            // lần thì subscription này vô dụng — xoá để khỏi thử lại ở mọi thông báo.
            if ($row !== null && in_array($status, [401, 403], true)
                && $this->subscriptions->incrementFailures($row) >= self::MAX_AUTH_FAILURES) {
                $this->subscriptions->delete($row);
            }

            Log::notice('Push delivery failed.', [
                'user_id' => $userId,
                'host' => parse_url($report->getEndpoint(), PHP_URL_HOST),
                'status' => $status,
                'reason' => $report->getReason(),
            ]);
        }
    }

    public function subscribe(int $userId, string $endpoint, string $publicKey, string $authToken, string $contentEncoding = 'aes128gcm'): PushSubscription
    {
        return $this->subscriptions->upsertForUser(
            $userId,
            $endpoint,
            $publicKey,
            $authToken,
            $contentEncoding !== '' ? $contentEncoding : 'aes128gcm',
        );
    }

    public function unsubscribe(int $userId, string $endpoint): void
    {
        $this->subscriptions->deleteByEndpoint($userId, $endpoint);
    }

    /**
     * TTL/độ ưu tiên theo loại tin: chat cần tới ngay hoặc bỏ; thông báo khác giữ tối đa 1 ngày
     * (mặc định thư viện là 4 tuần — tin cũ ùa về cùng lúc khi máy bật lại).
     *
     * @param  array<string, mixed>  $payload
     * @return array{TTL: int, urgency: string}
     */
    public function deliveryOptions(array $payload): array
    {
        $isChat = str_starts_with((string) ($payload['tag'] ?? ''), 'va-chat-');

        return [
            'TTL' => $isChat ? self::CHAT_TTL_SECONDS : self::DEFAULT_TTL_SECONDS,
            'urgency' => $isChat ? 'high' : 'normal',
        ];
    }

    private function client(): ?object
    {
        if (! class_exists(\Minishlink\WebPush\WebPush::class)) {
            return null;
        }

        try {
            return new \Minishlink\WebPush\WebPush(
                [
                    'VAPID' => [
                        'subject' => (string) config('services.webpush.subject'),
                        'publicKey' => (string) config('services.webpush.public_key'),
                        'privateKey' => (string) config('services.webpush.private_key'),
                    ],
                ],
                [],
                self::HTTP_TIMEOUT_SECONDS,
            );
        } catch (Throwable $e) {
            Log::warning('Cannot boot WebPush client.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
