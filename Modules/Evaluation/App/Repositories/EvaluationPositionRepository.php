<?php

namespace Modules\Evaluation\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationPosition;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationPositionRepositoryInterface;

class EvaluationPositionRepository implements EvaluationPositionRepositoryInterface
{
    public function all(): Collection
    {
        return EvaluationPosition::query()->orderBy('name')->get();
    }

    public function find(int $id): ?EvaluationPosition
    {
        return EvaluationPosition::query()->find($id);
    }

    public function findMany(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        return EvaluationPosition::query()->whereIn('id', $ids)->get();
    }

    public function create(array $data): EvaluationPosition
    {
        return EvaluationPosition::query()->create($data);
    }

    public function update(EvaluationPosition $position, array $data): EvaluationPosition
    {
        $position->update($data);

        return $position->fresh();
    }

    public function delete(EvaluationPosition $position): bool
    {
        return (bool) $position->delete();
    }
}
