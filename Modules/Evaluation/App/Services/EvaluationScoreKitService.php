<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
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
     * Bộ thang CHỈ ĐỌC cho form công việc / chấm điểm — không cần quyền
     * evaluation.manage_department.
     *
     * @return array{
     *     department_id: int,
     *     mode: string|null,
     *     lock_difficulty: bool,
     *     formula: array{weight: string, progress: string, quality: string},
     *     difficulty_levels: list<array{code: string, label: string, score: float}>,
     *     progress_levels: list<array{code: string, label: string, score: float}>,
     *     quality_levels: list<array{code: string, label: string, score: float}>
     * }
     */
    public function taskScalesForDepartment(int $departmentId): array
    {
        $kit = $this->kits->findByDepartment($departmentId);
        $formula = EvaluationScoreKit::normalizeFormula($kit?->formula);
        $weighted = $kit?->mode === EvaluationScoreKit::MODE_WEIGHTED_TASK;

        $difficulty = [];
        $progress = [];
        $quality = [];

        if ($weighted && $kit !== null) {
            $difficultySource = ! $kit->difficulty_use_default && $kit->difficultyCriterion
                ? $kit->difficultyCriterion->levels
                : $kit->weighted_task_levels;
            $progressSource = ! $kit->progress_use_default && $kit->progressCriterion
                ? $kit->progressCriterion->levels
                : $kit->progress_levels;
            $qualitySource = ! $kit->quality_use_default && $kit->qualityCriterion
                ? $kit->qualityCriterion->levels
                : $kit->quality_levels;

            $difficulty = EvaluationScoreKit::convertCriterionLevelsToFactors($difficultySource, 'difficulty');
            $progress = EvaluationScoreKit::convertCriterionLevelsToFactors($progressSource, 'progress');
            $quality = EvaluationScoreKit::convertCriterionLevelsToFactors($qualitySource, 'quality');
        }

        return [
            'department_id' => $departmentId,
            'mode' => $kit?->mode,
            'lock_difficulty' => $weighted && ($formula['lock_difficulty'] ?? 'off') === 'on',
            'formula' => [
                'weight' => $formula['weight'],
                'progress' => $formula['progress'],
                'quality' => $formula['quality'],
            ],
            'difficulty_criterion_id' => $kit?->difficulty_criterion_id,
            'difficulty_levels' => $difficulty,
            'progress_levels' => $progress,
            'quality_levels' => $quality,
        ];
    }

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

        $this->syncDifficultyToTaskType($kit, $userId);

        $criteria = $this->criteriaService->listForDepartment($departmentId);

        return $this->present($kit, $departmentId, $criteria);
    }

    /**
     * Cách 2 + đã chọn tiêu chí nguồn độ khó → cùng tiêu chí đó là option
     * trên form công việc, để lúc giao việc và lúc chấm kỳ nói cùng một thang.
     */
    private function syncDifficultyToTaskType(EvaluationScoreKit $kit, int $userId): void
    {
        if ($kit->mode !== EvaluationScoreKit::MODE_WEIGHTED_TASK) {
            return;
        }
        if ($kit->difficulty_use_default || ! $kit->difficulty_criterion_id) {
            return;
        }

        $this->criteriaService->assignAsTaskType(
            (int) $kit->difficulty_criterion_id,
            (int) $kit->department_id,
            $userId,
        );
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
            $payload['base_adjust_levels'] = EvaluationScoreKit::convertCriterionLevelsToPercent(
                is_array($data['base_adjust_levels']) ? $data['base_adjust_levels'] : null,
                true,
            );
        }

        if (array_key_exists('weighted_task_levels', $data)) {
            $payload['weighted_task_levels'] = EvaluationScoreKit::convertCriterionLevelsToFactors(
                is_array($data['weighted_task_levels']) ? $data['weighted_task_levels'] : null,
                'difficulty',
            );
        }

        if (array_key_exists('progress_levels', $data)) {
            $payload['progress_levels'] = EvaluationScoreKit::convertCriterionLevelsToFactors(
                is_array($data['progress_levels']) ? $data['progress_levels'] : null,
                'progress',
            );
        }

        if (array_key_exists('quality_levels', $data)) {
            $payload['quality_levels'] = EvaluationScoreKit::convertCriterionLevelsToFactors(
                is_array($data['quality_levels']) ? $data['quality_levels'] : null,
                'quality',
            );
        }

        if (array_key_exists('performance_levels', $data)) {
            $payload['performance_levels'] = EvaluationScoreKit::convertCriterionLevelsToPercent(
                is_array($data['performance_levels']) ? $data['performance_levels'] : null,
            );
        }

        if (array_key_exists('formula', $data)) {
            $formula = EvaluationScoreKit::normalizeFormula(
                is_array($data['formula']) ? $data['formula'] : null,
            );
            $payload['formula'] = $formula;
            $payload['use_project_importance'] = $formula['project'] === 'on';
        }

        $payload['kit_schema_version'] = EvaluationScoreKit::KIT_SCHEMA_VERSION;

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
            : $this->resolveSelectedScale($scaleRows, $kit?->classification_criterion_id, 2);
        $difficulty = $difficultyUsesDefault
            ? null
            : $this->resolveSelectedScale($scaleRows, $kit?->difficulty_criterion_id);
        $progress = $progressUsesDefault
            ? null
            : $this->resolveSelectedScale($scaleRows, $kit?->progress_criterion_id);
        $quality = $qualityUsesDefault
            ? null
            : $this->resolveSelectedScale($scaleRows, $kit?->quality_criterion_id);

        $classificationLevels = $this->criterionLevels($classification, true);
        $difficultyLevels = $this->criterionLevels($difficulty);
        $progressLevels = $this->criterionLevels($progress);
        $qualityLevels = $this->criterionLevels($quality);

        $formula = EvaluationScoreKit::normalizeFormula($kit?->formula);
        if ($kit !== null && empty($kit->formula)) {
            $formula['project'] = $kit->use_project_importance ? 'on' : 'off';
        }

        $baseAdjustLevels = $classificationLevels !== null
            ? EvaluationScoreKit::convertCriterionLevelsToPercent($classificationLevels, true)
            : EvaluationScoreKit::normalizeClassificationLevels($kit?->base_adjust_levels);
        $performanceLevels = $classificationLevels !== null
            ? EvaluationScoreKit::convertCriterionLevelsToPercent($classificationLevels)
            : EvaluationScoreKit::convertCriterionLevelsToPercent($kit?->performance_levels);
        $weightedLevels = $difficultyLevels !== null
            ? EvaluationScoreKit::convertCriterionLevelsToFactors($difficultyLevels, 'difficulty')
            : EvaluationScoreKit::convertCriterionLevelsToFactors(
                $kit?->weighted_task_levels,
                'difficulty',
            );
        $progressOut = $progressLevels !== null
            ? EvaluationScoreKit::convertCriterionLevelsToFactors($progressLevels, 'progress')
            : EvaluationScoreKit::convertCriterionLevelsToFactors($kit?->progress_levels, 'progress');
        $qualityOut = $qualityLevels !== null
            ? EvaluationScoreKit::convertCriterionLevelsToFactors($qualityLevels, 'quality')
            : EvaluationScoreKit::convertCriterionLevelsToFactors($kit?->quality_levels, 'quality');

        return [
            'id' => $kit?->id,
            'department_id' => $departmentId,
            'mode' => $kit?->mode,
            'kit_schema_version' => EvaluationScoreKit::KIT_SCHEMA_VERSION,
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
            'base_adjust_levels' => $baseAdjustLevels,
            'weighted_task_levels' => $weightedLevels,
            'progress_levels' => $progressOut,
            'quality_levels' => $qualityOut,
            'performance_levels' => $performanceLevels,
            'progress_bands' => EvaluationScoreKit::buildProgressBands($progressOut),
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
     * Chỉ lấy tiêu chí người dùng đã chọn. Không tự gán theo tên — thang 1–5
     * của tiêu chí hành vi/chất lượng không được lặng lẽ trở thành hệ số nhân.
     *
     * @param  Collection<int, array<string, mixed>>  $criteria
     */
    private function resolveSelectedScale(
        Collection $criteria,
        mixed $criterionId,
        int $minimumLevels = 1,
    ): ?array {
        if ($criterionId === null || $criterionId === '') {
            return null;
        }

        $selected = $criteria->first(
            fn (array $criterion) => (int) ($criterion['id'] ?? 0) === (int) $criterionId
                && count($criterion['levels'] ?? []) >= $minimumLevels,
        );

        return is_array($selected) ? $selected : null;
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
}
