<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        if (array_key_exists('task_base_score', $data)) {
            $payload['task_base_score'] = round((float) $data['task_base_score'], 2);
        }

        if (array_key_exists('quality_bonus_percent', $data)) {
            $payload['quality_bonus_percent'] = round((float) $data['quality_bonus_percent'], 2);
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

        foreach ([
            'classification_criterion_id',
            'difficulty_criterion_id',
            'progress_criterion_id',
            'quality_criterion_id',
        ] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $id = $data[$field];
            $payload[$field] = $id === null || $id === '' ? null : (int) $id;
        }

        foreach ([
            'classification_use_default' => 'classification_criterion_id',
            'difficulty_use_default' => 'difficulty_criterion_id',
            'progress_use_default' => 'progress_criterion_id',
            'quality_use_default' => 'quality_criterion_id',
        ] as $field => $criterionField) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $payload[$field] = (bool) $data[$field];
            if ($payload[$field]) {
                $payload[$criterionField] = null;
            }
        }

        if (array_key_exists('base_adjust_levels', $data)) {
            $payload['base_adjust_levels'] = EvaluationScoreKit::normalizeClassificationLevels(
                is_array($data['base_adjust_levels']) ? $data['base_adjust_levels'] : null,
            );
        }

        if (array_key_exists('weighted_task_levels', $data)) {
            $payload['weighted_task_levels'] = EvaluationScoreKit::normalizeLevels(
                is_array($data['weighted_task_levels']) ? $data['weighted_task_levels'] : null,
                EvaluationScoreKit::defaultWeightedTaskLevels(),
            );
        }

        if (array_key_exists('progress_levels', $data)) {
            $payload['progress_levels'] = EvaluationScoreKit::normalizeProgressLevels(
                is_array($data['progress_levels']) ? $data['progress_levels'] : null,
            );
        }

        if (array_key_exists('quality_levels', $data)) {
            $payload['quality_levels'] = EvaluationScoreKit::normalizeLevels(
                is_array($data['quality_levels']) ? $data['quality_levels'] : null,
                EvaluationScoreKit::defaultQualityLevels(),
            );
        }

        if (array_key_exists('performance_levels', $data)) {
            $payload['performance_levels'] = EvaluationScoreKit::normalizeLevels(
                is_array($data['performance_levels']) ? $data['performance_levels'] : null,
                EvaluationScoreKit::defaultPerformanceLevels(),
            );
        }

        if (array_key_exists('formula', $data)) {
            $formula = EvaluationScoreKit::normalizeFormula(
                is_array($data['formula']) ? $data['formula'] : null,
            );
            $payload['formula'] = $formula;
            $payload['use_project_importance'] = $formula['project'] === 'on';
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
        $scaleRows = $rows
            ->filter(fn (array $criterion) => $this->isUsableScale($criterion))
            ->values();
        $taskType = $rows->first(fn (array $c) => ! empty($c['use_for_task_type']));
        $classificationUsesDefault = (bool) ($kit?->classification_use_default ?? false);
        $difficultyUsesDefault = (bool) ($kit?->difficulty_use_default ?? false);
        $progressUsesDefault = (bool) ($kit?->progress_use_default ?? false);
        $qualityUsesDefault = (bool) ($kit?->quality_use_default ?? false);
        $classification = $classificationUsesDefault
            ? null
            : $this->resolveScaleCriterion(
                $scaleRows,
                $kit?->classification_criterion_id,
                ['xep loai', 'phan loai', 'muc do hoan thanh'],
                2,
            );
        $difficulty = $difficultyUsesDefault
            ? null
            : $this->resolveScaleCriterion(
                $scaleRows,
                $kit?->difficulty_criterion_id,
                ['do kho', 'muc do quan trong', 'trong so'],
                1,
                fn (array $criterion) => ! empty($criterion['use_for_task_type']),
            );
        $progress = $progressUsesDefault
            ? null
            : $this->resolveScaleCriterion(
                $scaleRows,
                $kit?->progress_criterion_id,
                ['tien do', 'dung han', 'deadline'],
            );
        $quality = $qualityUsesDefault
            ? null
            : $this->resolveScaleCriterion(
                $scaleRows,
                $kit?->quality_criterion_id,
                ['chat luong'],
            );

        $classificationLevels = $this->criterionLevels($classification, true);
        $difficultyLevels = $this->criterionLevels($difficulty);
        $progressLevels = $this->criterionLevels($progress);
        $qualityLevels = $this->criterionLevels($quality);

        $formula = EvaluationScoreKit::normalizeFormula($kit?->formula);
        if ($kit !== null && empty($kit->formula)) {
            $formula['project'] = $kit->use_project_importance ? 'on' : 'off';
        }

        return [
            'id' => $kit?->id,
            'department_id' => $departmentId,
            'mode' => $kit?->mode,
            'base_score' => $kit !== null ? (float) $kit->base_score : 100.0,
            'task_base_score' => $kit !== null && $kit->task_base_score !== null
                ? (float) $kit->task_base_score
                : 100.0,
            'quality_bonus_percent' => $kit !== null && $kit->quality_bonus_percent !== null
                ? (float) $kit->quality_bonus_percent
                : 5.0,
            'points_per_completed_task' => $kit !== null ? (float) $kit->points_per_completed_task : 0.0,
            'points_per_incomplete_task' => $kit !== null ? (float) $kit->points_per_incomplete_task : 0.0,
            'use_project_importance' => $formula['project'] === 'on',
            'formula' => $formula,
            'classification_criterion_id' => $classification ? (int) $classification['id'] : null,
            'classification_criterion' => $classification,
            'difficulty_criterion_id' => $difficulty ? (int) $difficulty['id'] : null,
            'difficulty_criterion' => $difficulty,
            'progress_criterion_id' => $progress ? (int) $progress['id'] : null,
            'progress_criterion' => $progress,
            'quality_criterion_id' => $quality ? (int) $quality['id'] : null,
            'quality_criterion' => $quality,
            'classification_use_default' => $classificationUsesDefault,
            'difficulty_use_default' => $difficultyUsesDefault,
            'progress_use_default' => $progressUsesDefault,
            'quality_use_default' => $qualityUsesDefault,
            'task_type_criterion_id' => is_array($taskType) ? ($taskType['id'] ?? null) : null,
            'task_type_criterion' => $taskType,
            'base_adjust_levels' => EvaluationScoreKit::normalizeClassificationLevels(
                $classificationLevels ?? $kit?->base_adjust_levels,
            ),
            'weighted_task_levels' => EvaluationScoreKit::normalizeLevels(
                $difficultyLevels ?? $kit?->weighted_task_levels,
                EvaluationScoreKit::defaultWeightedTaskLevels(),
            ),
            'progress_levels' => EvaluationScoreKit::normalizeProgressLevels(
                $progressLevels ?? $kit?->progress_levels,
            ),
            'quality_levels' => EvaluationScoreKit::normalizeLevels(
                $qualityLevels ?? $kit?->quality_levels,
                EvaluationScoreKit::defaultQualityLevels(),
            ),
            'performance_levels' => EvaluationScoreKit::normalizeLevels(
                $classificationLevels ?? $kit?->performance_levels,
                EvaluationScoreKit::defaultPerformanceLevels(),
            ),
        ];
    }

    private function isUsableScale(array $criterion): bool
    {
        return ($criterion['type'] ?? null) === 'scale'
            && ! empty($criterion['is_active'])
            && is_array($criterion['levels'] ?? null)
            && $criterion['levels'] !== [];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $criteria
     * @param  list<string>  $nameNeedles
     */
    private function resolveScaleCriterion(
        Collection $criteria,
        mixed $criterionId,
        array $nameNeedles,
        int $minimumLevels = 1,
        ?callable $preferred = null,
    ): ?array {
        $eligible = $criteria->filter(
            fn (array $criterion) => count($criterion['levels'] ?? []) >= $minimumLevels,
        );

        if ($criterionId !== null) {
            $selected = $eligible->first(
                fn (array $criterion) => (int) ($criterion['id'] ?? 0) === (int) $criterionId,
            );
            if (is_array($selected)) {
                return $selected;
            }
        }

        if ($preferred !== null) {
            $selected = $eligible->first($preferred);
            if (is_array($selected)) {
                return $selected;
            }
        }

        foreach ($nameNeedles as $needle) {
            $selected = $eligible->first(
                fn (array $criterion) => str_contains(
                    $this->searchableName((string) ($criterion['name'] ?? '')),
                    $needle,
                ),
            );
            if (is_array($selected)) {
                return $selected;
            }
        }

        return null;
    }

    /** @return list<array{code: string, label: string, score: float, sort_order?: int}>|null */
    private function criterionLevels(?array $criterion, bool $withSortOrder = false): ?array
    {
        if ($criterion === null) {
            return null;
        }

        $levels = [];
        foreach (array_values($criterion['levels'] ?? []) as $index => $level) {
            if (! is_array($level)) {
                continue;
            }

            $row = [
                'code' => (string) ($level['code'] ?? ''),
                'label' => (string) ($level['label'] ?? ''),
                'score' => (float) ($level['score'] ?? 0),
            ];
            if ($withSortOrder) {
                $row['sort_order'] = $index;
            }
            $levels[] = $row;
        }

        return $levels !== [] ? $levels : null;
    }

    private function searchableName(string $name): string
    {
        return mb_strtolower(Str::ascii(trim($name)));
    }
}
