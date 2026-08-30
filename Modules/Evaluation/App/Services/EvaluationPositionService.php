<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationPosition;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationPositionRepositoryInterface;

/**
 * "Vị trí đánh giá" — danh mục chức danh dùng chung toàn hệ thống. CHỈ ĐỌC —
 * không còn tạo/sửa/xoá tay, danh mục sẽ nối API VA-HRM sau này.
 */
class EvaluationPositionService
{
    public function __construct(
        private readonly EvaluationPositionRepositoryInterface $positions,
    ) {}

    public function list(): Collection
    {
        return $this->positions->all()->map(fn (EvaluationPosition $p) => $this->present($p))->values();
    }

    /** @return array<string, mixed> */
    public function present(EvaluationPosition $position): array
    {
        return [
            'id'          => $position->id,
            'name'        => $position->name,
            'kind'        => $position->kind,
            'description' => $position->description,
            'created_at'  => $position->created_at?->toIso8601String(),
        ];
    }
}
