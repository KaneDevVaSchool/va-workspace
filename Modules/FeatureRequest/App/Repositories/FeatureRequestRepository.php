<?php

namespace Modules\FeatureRequest\App\Repositories;

use Illuminate\Support\Collection;
use Modules\FeatureRequest\App\Models\FeatureRequest;
use Modules\FeatureRequest\App\Repositories\Contracts\FeatureRequestRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho module này.
 */
class FeatureRequestRepository implements FeatureRequestRepositoryInterface
{
    public function find(int $id): ?FeatureRequest
    {
        return FeatureRequest::query()->find($id);
    }

    public function create(array $data): FeatureRequest
    {
        return FeatureRequest::query()->create($data);
    }

    public function update(FeatureRequest $featureRequest, array $data): FeatureRequest
    {
        $featureRequest->update($data);

        return $featureRequest->refresh();
    }

    public function delete(FeatureRequest $featureRequest): bool
    {
        return (bool) $featureRequest->delete();
    }

    public function allWithRelations(?string $status = null): Collection
    {
        return FeatureRequest::query()
            ->with(['creator', 'reviewer', 'department'])
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get();
    }

    public function forCreator(int $userId): Collection
    {
        return FeatureRequest::query()
            ->with(['creator', 'reviewer', 'department'])
            ->where('created_by', $userId)
            ->orderByDesc('created_at')
            ->get();
    }
}
