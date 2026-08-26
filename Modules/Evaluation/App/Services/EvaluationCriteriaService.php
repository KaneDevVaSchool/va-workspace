<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;

class EvaluationCriteriaService
{
    public function __construct(
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
        private readonly EvaluationCriterionTypeRepositoryInterface $types,
    ) {}

    /** Danh sách tiêu chí của phòng ban, đã present sẵn cho API response. */
    public function listForDepartment(int $departmentId): Collection
    {
        return $this->criteria
            ->allByDepartment($departmentId)
            ->map(fn (EvaluationCriteria $c) => $this->present($c))
            ->values();
    }

    /** @return list<int> */
    public function idsForDepartment(int $departmentId): array
    {
        return $this->criteria->idsByDepartment($departmentId);
    }

    public function create(int $departmentId, int $createdBy, array $data): EvaluationCriteria
    {
        $allowHalf = (bool) ($data['allow_half'] ?? false);
        $normalized = $this->normalizeLevels($data['type'], $data['levels'] ?? [], $allowHalf);

        return $this->criteria->create([
            'department_id'     => $departmentId,
            'criterion_type_id' => $this->resolveTypeId($departmentId, $data['criterion_type_id'] ?? null),
            'name'              => trim($data['name']),
            'type'              => $data['type'],
            'description'       => isset($data['description']) ? trim($data['description']) : null,
            'levels'            => $normalized,
            'is_active'           => $data['is_active'] ?? true,
            'allow_half'          => $allowHalf,
            'use_in_evaluation'   => $data['use_in_evaluation'] ?? true,
            'sort_order'          => $data['sort_order'] ?? 0,
            'created_by'          => $createdBy,
            'updated_by'          => $createdBy,
        ]);
    }

    public function update(
        EvaluationCriteria $criterion,
        array $data,
        ?int $updatedBy = null,
    ): EvaluationCriteria {
        $type = $data['type'] ?? $criterion->type;
        $allowHalf = array_key_exists('allow_half', $data)
            ? (bool) $data['allow_half']
            : (bool) $criterion->allow_half;
        $normalized = $this->normalizeLevels(
            $type,
            $data['levels'] ?? $criterion->levels,
            $allowHalf,
        );

        $payload = [
            'name'        => trim($data['name'] ?? $criterion->name),
            'type'        => $type,
            'description' => array_key_exists('description', $data)
                ? (isset($data['description']) ? trim($data['description']) : null)
                : $criterion->description,
            'levels'             => $normalized,
            'is_active'          => $data['is_active'] ?? $criterion->is_active,
            'allow_half'         => $allowHalf,
            'use_in_evaluation'  => array_key_exists('use_in_evaluation', $data)
                ? (bool) $data['use_in_evaluation']
                : (bool) $criterion->use_in_evaluation,
            'sort_order'         => $data['sort_order'] ?? $criterion->sort_order,
        ];

        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }

        if (array_key_exists('criterion_type_id', $data)) {
            $payload['criterion_type_id'] = $this->resolveTypeId(
                (int) $criterion->department_id,
                $data['criterion_type_id'],
            );
        }

        return $this->criteria->update($criterion, $payload);
    }

    public function toggleActive(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        return $this->criteria->toggleActive($criterion, $updatedBy);
    }

    public function toggleUseInEvaluation(EvaluationCriteria $criterion, ?int $updatedBy = null): EvaluationCriteria
    {
        return $this->criteria->toggleUseInEvaluation($criterion, $updatedBy);
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

        $type = $criterion->criterionType;

        return [
            'id'                 => $criterion->id,
            'criterion_type_id'  => $criterion->criterion_type_id,
            'criterion_type'     => $type ? [
                'id'          => $type->id,
                'name'        => $type->name,
                'code'        => $type->code,
                'description' => $type->description,
            ] : null,
            'name'        => $criterion->name,
            'type'        => $criterion->type,
            'description' => $criterion->description,
            'levels'      => $levels,
            'level_count' => count($levels),
            'max_score'          => $criterion->max_score,
            'is_active'          => $criterion->is_active,
            'allow_half'         => (bool) $criterion->allow_half,
            'use_in_evaluation'  => (bool) $criterion->use_in_evaluation,
            'sort_order'         => $criterion->sort_order,
            'created_by'         => $criterion->created_by,
            'updated_by'         => $criterion->updated_by,
            'creator'            => $this->presentUser($criterion->creator),
            'updater'            => $this->presentUser($criterion->updater),
            'created_at'         => $criterion->created_at?->toIso8601String(),
            'updated_at'         => $criterion->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{id: int, name: string, email: string|null, avatar_url: string|null, department: array{id: int, name: string}|null}|null */
    private function presentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $department = $user->department;

        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar_url' => $user->avatar_url,
            'department' => $department ? [
                'id'   => $department->id,
                'name' => $department->name,
            ] : null,
        ];
    }

    private function resolveTypeId(int $departmentId, mixed $typeId): ?int
    {
        if ($typeId === null || $typeId === '') {
            return null;
        }

        $id = (int) $typeId;
        if ($id < 1) {
            return null;
        }

        return $this->types->findByDepartment($id, $departmentId)?->id;
    }

    /**
     * Chuẩn hoá mảng levels trước khi lưu:
     * - Loại bỏ mức trống.
     * - Scale: score dương, bước 1 hoặc 0.5 tuỳ allow_half.
     * - Behavior: score khác 0, có thể âm, cùng bước.
     *
     * @param  array<array{code?: string, label: string, description?: string, score: float|int}>  $levels
     * @return array<array{code: string, label: string, description: string, score: float}>
     */
    private function normalizeLevels(string $type, array $levels, bool $allowHalf = false): array
    {
        $result = [];
        $minScale = $allowHalf ? 0.5 : 1.0;

        foreach ($levels as $level) {
            $label = trim((string) ($level['label'] ?? ''));
            $raw = (float) ($level['score'] ?? 0);
            $score = $allowHalf ? round($raw * 2) / 2 : (float) round($raw);

            if ($label === '') {
                continue;
            }

            if ($type === 'scale' && $score < $minScale) {
                $score = $minScale;
            }

            if ($type === 'behavior' && $score == 0.0) {
                continue;
            }

            $result[] = [
                'code'        => strtoupper(trim((string) ($level['code'] ?? ''))),
                'label'       => $label,
                'description' => trim((string) ($level['description'] ?? '')),
                'score'       => $score,
            ];
        }

        return $result;
    }
}
