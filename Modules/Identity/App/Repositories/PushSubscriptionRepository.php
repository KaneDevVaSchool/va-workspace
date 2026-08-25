<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\PushSubscription;
use Modules\Identity\App\Repositories\Contracts\PushSubscriptionRepositoryInterface;

class PushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    public function upsertForUser(int $userId, string $endpoint, string $publicKey, string $authToken, string $contentEncoding = 'aes128gcm'): PushSubscription
    {
        $hash = PushSubscription::hashEndpoint($endpoint);

        return PushSubscription::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'endpoint_hash' => $hash,
            ],
            [
                'endpoint' => $endpoint,
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $contentEncoding,
            ],
        );
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

    public function forUser(int $userId): Collection
    {
        return PushSubscription::query()
            ->where('user_id', $userId)
            ->get();
    }
}
