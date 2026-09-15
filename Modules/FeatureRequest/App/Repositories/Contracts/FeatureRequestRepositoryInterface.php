<?php

namespace Modules\FeatureRequest\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\FeatureRequest\App\Models\FeatureRequest;

interface FeatureRequestRepositoryInterface
{
    public function find(int $id): ?FeatureRequest;

    public function create(array $data): FeatureRequest;

    public function update(FeatureRequest $featureRequest, array $data): FeatureRequest;

    public function delete(FeatureRequest $featureRequest): bool;

    /**
     * Toàn bộ ghi nhận, mới nhất trước, kèm người tạo/người xử lý/phòng ban
     * — dùng cho superadmin tự group theo phòng ban ở tầng Service.
     */
    public function allWithRelations(?string $status = null): Collection;

    /**
     * Ghi nhận của một người dùng, mới nhất trước.
     */
    public function forCreator(int $userId): Collection;
}
