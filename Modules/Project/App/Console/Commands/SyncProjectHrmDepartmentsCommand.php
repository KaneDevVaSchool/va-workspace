<?php

namespace Modules\Project\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Project\App\Services\ProjectDepartmentCatalogRemapper;
use Modules\Project\App\Services\ProjectHrmDepartmentMapper;

class SyncProjectHrmDepartmentsCommand extends Command
{
    protected $signature = 'project:sync-hrm-departments
                            {--skip-catalog-sync : Không gọi đồng bộ danh mục HRM}
                            {--skip-remap : Chỉ map theo người phụ trách, không gom id cũ}
                            {--skip-lead-map : Không ghi đè phòng ban theo người phụ trách HRM}';

    protected $description = 'Đồng bộ danh mục phòng ban HRM, gom id cũ trên dự án, map theo người phụ trách.';

    public function handle(
        HrmDepartmentSyncService $hrmSync,
        ProjectDepartmentCatalogRemapper $remapper,
        ProjectHrmDepartmentMapper $leadMapper,
    ): int {
        if (! $this->option('skip-catalog-sync')) {
            if (! HrmDepartmentSyncService::isConfigured()) {
                $this->warn('HRM chưa cấu hình — bỏ qua đồng bộ danh mục.');

                return self::FAILURE;
            }

            $hrmSync->syncDepartmentsFromHrm();
            $this->info('Đã đồng bộ danh mục phòng ban từ HRM.');
        }

        if (! $this->option('skip-remap')) {
            $stats = $remapper->remapAll();
            $this->info(sprintf(
                'Gom id phòng ban: %d cặp map, %d dự án cập nhật cột, %d dự án sửa pivot, %d task, %d scope.',
                $stats['mapped_pairs'],
                $stats['projects_updated'],
                $stats['pivot_rows'],
                $stats['tasks_updated'],
                $stats['scopes_updated'],
            ));
        }

        if (! $this->option('skip-lead-map')) {
            $updated = $leadMapper->mapAll();
            $this->info("Đã map phòng ban HRM (người phụ trách) cho {$updated} dự án.");
        }

        return self::SUCCESS;
    }
}
