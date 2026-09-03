<?php

namespace Modules\Evaluation\App\Console;

use Illuminate\Console\Command;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Services\EvaluationConfigVersionService;
use Modules\Evaluation\App\Services\EvaluationScoreKitService;

class RepairCach2Command extends Command
{
    protected $signature = 'evaluation:repair-cach2 {--dry-run : Chỉ in, không ghi}';

    protected $description = 'Chuẩn hóa hệ số Cách 2 (schema v2) trên cấu hình sống và chốt phiên bản mới. Snapshot cũ không đụng.';

    public function handle(
        EvaluationScoreKitService $kits,
        EvaluationConfigVersionService $versions,
    ): int {
        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('Dry-run — không ghi cơ sở dữ liệu.');
        }

        $updatedKits = 0;
        foreach (EvaluationScoreKit::query()->orderBy('id')->get() as $kit) {
            $before = [
                'progress' => $kit->progress_levels,
                'quality' => $kit->quality_levels,
                'performance' => $kit->performance_levels,
                'schema' => $kit->kit_schema_version,
            ];
            $progress = EvaluationScoreKit::convertCriterionLevelsToFactors($kit->progress_levels, 'progress');
            $quality = EvaluationScoreKit::convertCriterionLevelsToFactors($kit->quality_levels, 'quality');
            $difficulty = EvaluationScoreKit::convertCriterionLevelsToFactors($kit->weighted_task_levels, 'difficulty');
            $performance = EvaluationScoreKit::convertCriterionLevelsToPercent($kit->performance_levels);
            $baseAdjust = EvaluationScoreKit::convertCriterionLevelsToPercent($kit->base_adjust_levels, true);

            $this->line(sprintf(
                'Kit #%d phòng %d · schema %s → 2 · tiến độ max %s → %s · xếp loại max %s → %s',
                $kit->id,
                $kit->department_id,
                $before['schema'] ?? 1,
                $this->maxScore($before['progress']),
                $this->maxScore($progress),
                $this->maxScore($before['performance']),
                $this->maxScore($performance),
            ));

            if (! $dry) {
                $kit->fill([
                    'kit_schema_version' => EvaluationScoreKit::KIT_SCHEMA_VERSION,
                    'progress_levels' => $progress,
                    'quality_levels' => $quality,
                    'weighted_task_levels' => $difficulty,
                    'performance_levels' => $performance,
                    'base_adjust_levels' => $baseAdjust,
                ])->save();
            }
            $updatedKits++;
        }

        $published = 0;
        $departmentIds = EvaluationConfigVersion::query()
            ->where('status', EvaluationConfigVersion::STATUS_ACTIVE)
            ->pluck('department_id')
            ->unique();

        foreach ($departmentIds as $departmentId) {
            $active = $versions->activeForDepartment((int) $departmentId);
            $schema = (int) ($active?->kit_snapshot['kit_schema_version'] ?? 1);
            $publisher = (int) ($active?->published_by
                ?? EvaluationScoreKit::query()->where('department_id', $departmentId)->value('updated_by')
                ?? 0);

            $this->line(sprintf(
                'Phiên bản phòng %d · schema snapshot %d → chốt v2 mới (publisher %s)',
                $departmentId,
                $schema,
                $publisher > 0 ? (string) $publisher : 'bỏ qua — thiếu người chốt',
            ));

            if ($dry || $publisher < 1) {
                continue;
            }

            $versions->publish(
                (int) $departmentId,
                $publisher,
                'Chuẩn hoá hệ số Cách 2',
            );
            $published++;
        }

        $this->info(sprintf(
            'Xong. %d khung sống, %d phiên bản mới%s.',
            $updatedKits,
            $published,
            $dry ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }

    /** @param  mixed  $levels */
    private function maxScore($levels): string
    {
        if (! is_array($levels) || $levels === []) {
            return '—';
        }
        $scores = [];
        foreach ($levels as $row) {
            if (is_array($row) && isset($row['score'])) {
                $scores[] = (float) $row['score'];
            }
        }

        return $scores === [] ? '—' : (string) max($scores);
    }
}
