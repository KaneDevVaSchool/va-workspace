<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationPosition;

interface EvaluationPositionRepositoryInterface
{
    /** Toàn bộ vị trí đánh giá — danh mục dùng chung toàn hệ thống. */
    public function all(): Collection;

    public function find(int $id): ?EvaluationPosition;

    /** @param  array<int>  $ids */
    public function findMany(array $ids): Collection;

    public function create(array $data): EvaluationPosition;

    public function update(EvaluationPosition $position, array $data): EvaluationPosition;

    public function delete(EvaluationPosition $position): bool;
}
