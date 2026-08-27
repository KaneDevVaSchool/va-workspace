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

    public function findMany(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        return EvaluationPosition::query()->whereIn('id', $ids)->get();
    }
}
