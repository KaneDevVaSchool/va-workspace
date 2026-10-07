<?php

namespace Modules\Evaluation\App\Console;

use Illuminate\Console\Command;
use Modules\Evaluation\App\Services\EvaluationDepartmentCatalogRemapper;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Throwable;

class RemapEvaluationHrmDepartmentsCommand extends Command
{
    protected $signature = 'evaluation:remap-hrm-departments
                            {--skip-catalog-sync : Không gọi đồng bộ danh mục phòng ban từ HRM}
                            {--dry-run : Chạy thử trong transaction rồi rollback — chỉ in thống kê}';

    protected $description = 'Gom tiêu chí / khung chấm điểm / sự kiện đánh giá từ id phòng ban cũ sang danh mục HRM.';

    public function handle(
        HrmDepartmentSyncService $hrmSync,
        EvaluationDepartmentCatalogRemapper $remapper,
    ): int {
        if (! $this->option('skip-catalog-sync')) {
            if (! HrmDepartmentSyncService::isConfigured()) {
                $this->warn('HRM chưa cấu hình — chạy lại sau khi có HRM_DB_*.');

                return self::FAILURE;
            }

            $hrmSync->syncDepartmentsFromHrm();
            $this->info('Đã đồng bộ danh mục phòng ban từ HRM.');
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry-run: thống kê dưới đây — không ghi DB.');
        }

        try {
            $stats = $remapper->remapAll($dryRun);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Chỉ số', 'Giá trị'],
            collect($stats)->map(fn ($value, $key) => [$key, (string) $value])->values()->all(),
        );

        if ($dryRun) {
            $this->info('Dry-run hoàn tất (đã rollback).');
        } else {
            $this->info('Đã map tiêu chí đánh giá sang phòng ban HRM.');
        }

        return self::SUCCESS;
    }
}
