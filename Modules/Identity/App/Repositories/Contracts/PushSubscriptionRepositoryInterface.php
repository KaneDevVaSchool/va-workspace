<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\PushSubscription;

interface PushSubscriptionRepositoryInterface
{
    /**
     * Một endpoint (một trình duyệt) chỉ thuộc về một người: bản ghi cùng endpoint của
     * user khác bị xoá, và mỗi user chỉ giữ tối đa $maxPerUser subscription mới nhất.
     */
    public function upsertForUser(int $userId, string $endpoint, string $publicKey, string $authToken, string $contentEncoding = 'aes128gcm', int $maxPerUser = 10): PushSubscription;

    public function deleteByEndpoint(int $userId, string $endpoint): void;

    public function delete(PushSubscription $subscription): void;

    /** Tăng bộ đếm lỗi xác thực và trả về giá trị mới. */
    public function incrementFailures(PushSubscription $subscription): int;

    public function resetFailures(PushSubscription $subscription): void;

    /** @return Collection<int, PushSubscription> */
    public function forUser(int $userId): Collection;
}
