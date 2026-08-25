<?php

namespace Modules\Identity\App\Services;

use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Models\PushSubscription;
use Modules\Identity\App\Repositories\Contracts\PushSubscriptionRepositoryInterface;
use Throwable;

class WebPushService
{
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
     * @param  array{title: string, body: string, url: string, tag?: string}  $payload
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
                );
            } catch (Throwable $e) {
                Log::warning('Invalid push subscription.', [
                    'subscription_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
                $this->subscriptions->delete($row);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $this->deleteByEndpoint($userId, $report->getEndpoint());
                continue;
            }

            if (! $report->isSuccess()) {
                Log::notice('Push delivery failed.', [
                    'user_id' => $userId,
                    'reason' => $report->getReason(),
                ]);
            }
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

    private function deleteByEndpoint(int $userId, string $endpoint): void
    {
        $this->subscriptions->deleteByEndpoint($userId, $endpoint);
    }

    private function client(): ?object
    {
        if (! class_exists(\Minishlink\WebPush\WebPush::class)) {
            return null;
        }

        try {
            return new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject' => (string) config('services.webpush.subject'),
                    'publicKey' => (string) config('services.webpush.public_key'),
                    'privateKey' => (string) config('services.webpush.private_key'),
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Cannot boot WebPush client.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
