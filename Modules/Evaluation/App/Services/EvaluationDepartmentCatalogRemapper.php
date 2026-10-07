<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Facades\DB;
use Modules\Project\App\Services\ProjectDepartmentCatalogRemapper;

/**
 * Chuyển tiêu chí / khung chấm điểm / sự kiện đánh giá từ id phòng ban org-unit
 * cũ sang id danh mục HRM (cùng logic gom id với dự án).
 */
class EvaluationDepartmentCatalogRemapper
{
    public function __construct(
        private readonly ProjectDepartmentCatalogRemapper $departmentMap,
    ) {}

    /**
     * @return array{
     *     mapped_pairs: int,
     *     pairs_processed: int,
     *     criterion_types_moved: int,
     *     criterion_types_merged: int,
     *     criteria_moved: int,
     *     criteria_deduped: int,
     *     score_kits_moved: int,
     *     score_kits_dropped: int,
     *     config_versions_updated: int,
     *     events_updated: int
     * }
     */
    public function remapAll(bool $dryRun = false): array
    {
        $map = $this->departmentMap->buildIdMap();
        $stats = [
            'mapped_pairs' => count($map),
            'pairs_processed' => 0,
            'criterion_types_moved' => 0,
            'criterion_types_merged' => 0,
            'criteria_moved' => 0,
            'criteria_deduped' => 0,
            'score_kits_moved' => 0,
            'score_kits_dropped' => 0,
            'config_versions_updated' => 0,
            'events_updated' => 0,
        ];

        $run = function () use ($map, &$stats, $dryRun): void {
            foreach ($map as $fromId => $toId) {
                $fromId = (int) $fromId;
                $toId = (int) $toId;
                if ($fromId === $toId) {
                    continue;
                }

                $stats['pairs_processed']++;

                $typeStats = $this->remapCriterionTypes($fromId, $toId, $dryRun);
                $stats['criterion_types_moved'] += $typeStats['moved'];
                $stats['criterion_types_merged'] += $typeStats['merged'];

                $criteriaStats = $this->remapCriteria($fromId, $toId, $dryRun);
                $stats['criteria_moved'] += $criteriaStats['moved'];
                $stats['criteria_deduped'] += $criteriaStats['deduped'];

                $kitResult = $this->remapScoreKit($fromId, $toId, $dryRun);
                if ($kitResult === 'moved') {
                    $stats['score_kits_moved']++;
                } elseif ($kitResult === 'dropped_duplicate') {
                    $stats['score_kits_dropped']++;
                }

                $stats['config_versions_updated'] += $this->updateDepartmentColumn(
                    'evaluation_config_versions',
                    $fromId,
                    $toId,
                    $dryRun,
                );
                $stats['events_updated'] += $this->updateDepartmentColumn(
                    'evaluation_events',
                    $fromId,
                    $toId,
                    $dryRun,
                );
            }
        };

        if ($dryRun) {
            try {
                DB::transaction(function () use ($run): void {
                    $run();
                    throw new RemapDryRunRollback;
                });
            } catch (RemapDryRunRollback) {
                // Chỉ rollback — thống kê $stats đã cập nhật trong $run.
            }
        } else {
            DB::transaction($run);
        }

        return $stats;
    }

    /**
     * @return array{moved: int, merged: int}
     */
    private function remapCriterionTypes(int $fromId, int $toId, bool $dryRun): array
    {
        $moved = 0;
        $merged = 0;

        $types = DB::table('evaluation_criterion_types')
            ->where('department_id', $fromId)
            ->orderBy('id')
            ->get();

        foreach ($types as $type) {
            $existing = DB::table('evaluation_criterion_types')
                ->where('department_id', $toId)
                ->where('code', $type->code)
                ->first();

            if ($existing !== null) {
                DB::table('evaluation_criteria')
                    ->where('criterion_type_id', $type->id)
                    ->update(['criterion_type_id' => $existing->id]);
                DB::table('evaluation_criterion_types')->where('id', $type->id)->delete();
                $merged++;
            } else {
                DB::table('evaluation_criterion_types')
                    ->where('id', $type->id)
                    ->update(['department_id' => $toId]);
                $moved++;
            }
        }

        return ['moved' => $moved, 'merged' => $merged];
    }

    /**
     * @return array{moved: int, deduped: int}
     */
    private function remapCriteria(int $fromId, int $toId, bool $dryRun): array
    {
        $moved = 0;
        $deduped = 0;

        $rows = DB::table('evaluation_criteria')
            ->where('department_id', $fromId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $normalized = mb_strtolower(trim((string) $row->name));
            $duplicateId = DB::table('evaluation_criteria')
                ->where('department_id', $toId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                ->value('id');

            if ($duplicateId !== null) {
                $this->repointCriteriaReferences((int) $row->id, (int) $duplicateId);
                DB::table('evaluation_criteria')->where('id', $row->id)->delete();
                $deduped++;
                continue;
            }

            DB::table('evaluation_criteria')
                ->where('id', $row->id)
                ->update(['department_id' => $toId]);
            $moved++;
        }

        return ['moved' => $moved, 'deduped' => $deduped];
    }

    private function repointCriteriaReferences(int $fromCriterionId, int $toCriterionId): void
    {
        DB::table('evaluation_score_kits')
            ->where('classification_criterion_id', $fromCriterionId)
            ->update(['classification_criterion_id' => $toCriterionId]);
    }

    /** @return 'none'|'moved'|'dropped_duplicate' */
    private function remapScoreKit(int $fromId, int $toId, bool $dryRun): string
    {
        $fromKit = DB::table('evaluation_score_kits')->where('department_id', $fromId)->first();
        if ($fromKit === null) {
            return 'none';
        }

        $toKit = DB::table('evaluation_score_kits')->where('department_id', $toId)->exists();
        if ($toKit) {
            DB::table('evaluation_score_kits')->where('department_id', $fromId)->delete();

            return 'dropped_duplicate';
        }

        DB::table('evaluation_score_kits')
            ->where('department_id', $fromId)
            ->update(['department_id' => $toId]);

        return 'moved';
    }

    private function updateDepartmentColumn(string $table, int $fromId, int $toId, bool $dryRun): int
    {
        return DB::table($table)
            ->where('department_id', $fromId)
            ->update(['department_id' => $toId]);
    }
}
