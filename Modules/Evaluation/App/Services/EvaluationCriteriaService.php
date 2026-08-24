<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;

class EvaluationCriteriaService
{
    public function __construct(
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
    ) {}

    /** Danh sách tiêu chí của phòng ban, đã present sẵn cho API response. */
    public function listForDepartment(int $departmentId): Collection
    {
        return $this->criteria
            ->allByDepartment($departmentId)
            ->map(fn (EvaluationCriteria $c) => $this->present($c))
            ->values();
    }

    public function create(int $departmentId, int $createdBy, array $data): EvaluationCriteria
    {
        $normalized = $this->normalizeLevels($data['type'], $data['levels'] ?? []);

        return $this->criteria->create([
            'department_id' => $departmentId,
            'name'          => trim($data['name']),
            'type'          => $data['type'],
            'description'   => isset($data['description']) ? trim($data['description']) : null,
            'levels'        => $normalized,
            'is_active'     => $data['is_active'] ?? true,
            'sort_order'    => $data['sort_order'] ?? 0,
            'created_by'    => $createdBy,
        ]);
    }

    public function update(
        EvaluationCriteria $criterion,
        array $data,
    ): EvaluationCriteria {
        $normalized = $this->normalizeLevels(
            $data['type'] ?? $criterion->type,
            $data['levels'] ?? $criterion->levels,
        );

        return $this->criteria->update($criterion, [
            'name'        => trim($data['name'] ?? $criterion->name),
            'type'        => $data['type'] ?? $criterion->type,
            'description' => array_key_exists('description', $data)
                ? (isset($data['description']) ? trim($data['description']) : null)
                : $criterion->description,
            'levels'      => $normalized,
            'is_active'   => $data['is_active'] ?? $criterion->is_active,
            'sort_order'  => $data['sort_order'] ?? $criterion->sort_order,
        ]);
    }

    public function toggleActive(EvaluationCriteria $criterion): EvaluationCriteria
    {
        return $this->criteria->toggleActive($criterion);
    }

    public function delete(EvaluationCriteria $criterion): bool
    {
        return $this->criteria->delete($criterion);
    }

    /**
     * Tìm tiêu chí theo id + phòng ban, trả 404 JsonResponse nếu không tìm thấy.
     * Controller dùng hàm này để tránh lặp pattern kiểm tra null.
     *
     * @return \Modules\Evaluation\App\Models\EvaluationCriteria|\Illuminate\Http\JsonResponse
     */
    public function findByDepartmentOrFail(int $id, int $departmentId)
    {
        $criterion = $this->criteria->findByDepartment($id, $departmentId);

        if ($criterion === null) {
            return response()->json(['message' => 'Không tìm thấy tiêu chí đánh giá.'], 404);
        }

        return $criterion;
    }

    public function reorder(int $departmentId, array $orderedIds): void
    {
        $this->criteria->reorder($departmentId, $orderedIds);
    }

    /** Trả về mảng present cho JSON response. */
    public function present(EvaluationCriteria $criterion): array
    {
        $levels = $criterion->levels ?? [];

        return [
            'id'          => $criterion->id,
            'name'        => $criterion->name,
            'type'        => $criterion->type,
            'description' => $criterion->description,
            'levels'      => $levels,
            'level_count' => count($levels),
            'max_score'   => $criterion->max_score,
            'is_active'   => $criterion->is_active,
            'sort_order'  => $criterion->sort_order,
            'created_by'  => $criterion->created_by,
            'created_at'  => $criterion->created_at?->toIso8601String(),
            'updated_at'  => $criterion->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Chuẩn hoá mảng levels trước khi lưu:
     * - Loại bỏ mức trống.
     * - Scale: score phải là số nguyên dương.
     * - Behavior: score khác 0, có thể âm.
     * Thứ tự giữ nguyên theo input (người dùng tự sắp xếp trên UI).
     *
     * @param  array<array{label: string, score: int}>  $levels
     * @return array<array{label: string, score: int}>
     */
    private function normalizeLevels(string $type, array $levels): array
    {
        $result = [];

        foreach ($levels as $level) {
            $label = trim((string) ($level['label'] ?? ''));
            $score = (int) ($level['score'] ?? 0);

            if ($label === '') {
                continue;
            }

            if ($type === 'scale' && $score < 1) {
                $score = 1;
            }

            if ($type === 'behavior' && $score === 0) {
                continue;
            }

            $result[] = ['label' => $label, 'score' => $score];
        }

        return $result;
    }
}
