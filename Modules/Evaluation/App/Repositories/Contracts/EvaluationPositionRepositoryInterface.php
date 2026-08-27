<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface EvaluationPositionRepositoryInterface
{
    /** Toàn bộ vị trí đánh giá — danh mục dùng chung toàn hệ thống. */
    public function all(): Collection;

    /** @param  array<int>  $ids */
    public function findMany(array $ids): Collection;
}
