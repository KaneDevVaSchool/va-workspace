<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationPosition;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationPositionRepositoryInterface;

/**
 * "Vị trí đánh giá" — danh mục chức danh dùng chung toàn hệ thống, gán N-N
 * vào EvaluationTemplate. Xem plans/2026-08-26-mau-danh-gia.md (PR3).
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

    public function create(int $createdBy, array $data): EvaluationPosition
    {
        return $this->positions->create([
            'name'        => trim($data['name']),
            'kind'        => $data['kind'] ?? EvaluationPosition::KIND_POSITION,
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'created_by'  => $createdBy,
        ]);
    }

    public function update(EvaluationPosition $position, array $data): EvaluationPosition
    {
        $payload = [
            'name' => trim($data['name'] ?? $position->name),
            'kind' => $data['kind'] ?? $position->kind,
            'description' => array_key_exists('description', $data)
                ? (isset($data['description']) ? trim($data['description']) : null)
                : $position->description,
        ];

        return $this->positions->update($position, $payload);
    }

    /**
     * @return \Modules\Evaluation\App\Models\EvaluationPosition|\Illuminate\Http\JsonResponse
     */
    public function findOrFail(int $id)
    {
        $position = $this->positions->find($id);

        if ($position === null) {
            return response()->json(['message' => 'Không tìm thấy vị trí đánh giá.'], 404);
        }

        return $position;
    }

    public function delete(EvaluationPosition $position): bool
    {
        return $this->positions->delete($position);
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
