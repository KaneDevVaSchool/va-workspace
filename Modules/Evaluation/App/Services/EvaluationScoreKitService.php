<?php

namespace Modules\Evaluation\App\Services;

use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationScoreKitRepositoryInterface;

class EvaluationScoreKitService
{
    public function __construct(
        private readonly EvaluationScoreKitRepositoryInterface $kits,
        private readonly EvaluationCriteriaService $criteriaService,
        private readonly EvaluationCriterionTypeService $types,
    ) {}

    /**
     * @return array{
     *     kit: array<string, mixed>,
     *     criteria: list<array<string, mixed>>,
     *     types: list<array<string, mixed>>
     * }
     */
    public function showForDepartment(int $departmentId): array
    {
        $kit = $this->kits->findByDepartment($departmentId);
        $criteria = $this->criteriaService->listForDepartment($departmentId);

        return [
            'kit' => $this->present($kit, $departmentId, $criteria),
            'criteria' => $criteria->values()->all(),
            'types' => $this->types->listForDepartment($departmentId)->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsert(int $departmentId, int $userId, array $data): array
    {
        $payload = $this->normalizedPayload($data);
        $existing = $this->kits->findByDepartment($departmentId);

        if ($existing === null) {
            $payload['department_id'] = $departmentId;
            $payload['created_by'] = $userId;
            $payload['updated_by'] = $userId;
            $kit = $this->kits->create($payload);
        } else {
            $payload['updated_by'] = $userId;
            $kit = $this->kits->update($existing, $payload);
        }

        $criteria = $this->criteriaService->listForDepartment($departmentId);

        return $this->present($kit, $departmentId, $criteria);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $data): array
    {
        $payload = [];

        if (array_key_exists('mode', $data)) {
            $mode = $data['mode'];
            $payload['mode'] = $mode === null || $mode === ''
                ? null
                : (string) $mode;
        }

        if (array_key_exists('base_score', $data)) {
            $payload['base_score'] = round((float) $data['base_score'], 2);
        }

        if (array_key_exists('points_per_completed_task', $data)) {
            $payload['points_per_completed_task'] = round((float) $data['points_per_completed_task'], 2);
        }

        if (array_key_exists('points_per_incomplete_task', $data)) {
            $payload['points_per_incomplete_task'] = round((float) $data['points_per_incomplete_task'], 2);
        }

        if (array_key_exists('use_project_importance', $data)) {
            $payload['use_project_importance'] = (bool) $data['use_project_importance'];
        }

        if (array_key_exists('classification_criterion_id', $data)) {
            $id = $data['classification_criterion_id'];
            $payload['classification_criterion_id'] = $id === null || $id === ''
                ? null
                : (int) $id;
        }

        return $payload;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    public function present(
        ?EvaluationScoreKit $kit,
        int $departmentId,
        $criteria = [],
    ): array {
        $rows = collect($criteria);
        $taskType = $rows->first(fn (array $c) => ! empty($c['use_for_task_type']));
        $classificationId = $kit?->classification_criterion_id;
        $classification = $classificationId
            ? $rows->first(fn (array $c) => (int) $c['id'] === (int) $classificationId)
            : null;

        if ($classification === null && $kit?->classificationCriterion instanceof EvaluationCriteria) {
            $classification = $this->criteriaService->present($kit->classificationCriterion);
        }

        return [
            'id' => $kit?->id,
            'department_id' => $departmentId,
            'mode' => $kit?->mode,
            'base_score' => $kit !== null ? (float) $kit->base_score : 100.0,
            'points_per_completed_task' => $kit !== null ? (float) $kit->points_per_completed_task : 0.0,
            'points_per_incomplete_task' => $kit !== null ? (float) $kit->points_per_incomplete_task : 0.0,
            'use_project_importance' => $kit !== null ? (bool) $kit->use_project_importance : true,
            'classification_criterion_id' => $classificationId ? (int) $classificationId : null,
            'classification_criterion' => $classification,
            'task_type_criterion_id' => is_array($taskType) ? ($taskType['id'] ?? null) : null,
            'task_type_criterion' => $taskType,
        ];
    }
}
