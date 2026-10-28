<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\PushSubscription;
use Modules\Identity\App\Repositories\Contracts\PushSubscriptionRepositoryInterface;

class PushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    public function upsertForUser(int $userId, string $endpoint, string $publicKey, string $authToken, string $contentEncoding = 'aes128gcm', int $maxPerUser = 10): PushSubscription
    {
        $hash = PushSubscription::hashEndpoint($endpoint);

        // Cùng trình duyệt đăng nhập người khác: endpoint này không còn thuộc người trước.
        PushSubscription::query()
            ->where('endpoint_hash', $hash)
            ->where('user_id', '!=', $userId)
            ->delete();

        $attributes = [
            'endpoint' => $endpoint,
            'public_key' => $publicKey,
            'auth_token' => $authToken,
            'content_encoding' => $contentEncoding,
            'failed_count' => 0,
        ];

        try {
            $subscription = PushSubscription::query()->updateOrCreate(
                ['user_id' => $userId, 'endpoint_hash' => $hash],
                $attributes,
            );
        } catch (UniqueConstraintViolationException) {
            // Hai yêu cầu đăng ký cùng endpoint chạy đua: bản ghi đã được tạo, chỉ cần cập nhật.
            $subscription = PushSubscription::query()->updateOrCreate(
                ['user_id' => $userId, 'endpoint_hash' => $hash],
                $attributes,
            );
        }

        $this->pruneOldest($userId, $maxPerUser);

        return $subscription;
    }

    public function deleteByEndpoint(int $userId, string $endpoint): void
    {
        PushSubscription::query()
            ->where('user_id', $userId)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->delete();
    }

    public function delete(PushSubscription $subscription): void
    {
        $subscription->delete();
    }

    public function incrementFailures(PushSubscription $subscription): int
    {
        $subscription->increment('failed_count');

        return (int) $subscription->failed_count;
    }

    public function resetFailures(PushSubscription $subscription): void
    {
        $subscription->forceFill(['failed_count' => 0])->save();
    }

    public function forUser(int $userId): Collection
    {
        return PushSubscription::query()
            ->where('user_id', $userId)
            ->get();
    }

    private function pruneOldest(int $userId, int $keep): void
    {
        if ($keep < 1) {
            return;
        }

        $staleIds = PushSubscription::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            PushSubscription::query()->whereIn('id', $staleIds)->delete();
        }
    }
}
