<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\PushSubscription;

interface PushSubscriptionRepositoryInterface
{
    public function upsertForUser(int $userId, string $endpoint, string $publicKey, string $authToken, string $contentEncoding = 'aes128gcm'): PushSubscription;

    public function deleteByEndpoint(int $userId, string $endpoint): void;

    public function delete(PushSubscription $subscription): void;

    /** @return Collection<int, PushSubscription> */
    public function forUser(int $userId): Collection;
}
