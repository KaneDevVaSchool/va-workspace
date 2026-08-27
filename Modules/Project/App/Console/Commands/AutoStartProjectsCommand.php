<?php

namespace Modules\Project\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Project\App\Services\ProjectService;

/**
 * Tự động chuyển trạng thái dự án "Lên kế hoạch" sang "Đang thực hiện" khi
 * đến ngày bắt đầu — chỉ chạy nếu cấu hình auto_start_on_begin_date đang bật
 * (xem ProjectSetting/ProjectSettings.vue). Command chỉ gọi Service, không
 * đụng Eloquent trực tiếp (Service → Repository theo đúng pattern chung).
 *
 * Đăng ký lịch chạy trong ProjectServiceProvider::boot() — dailyAt('00:05').
 */
class AutoStartProjectsCommand extends Command
{
    protected $signature = 'project:auto-start';

    protected $description = 'Tự động chuyển dự án sang "Đang thực hiện" khi đến ngày bắt đầu (nếu cấu hình bật).';

    public function handle(ProjectService $service): int
    {
        $updated = $service->autoStartEligibleProjects();

        $this->info("Đã tự động chuyển {$updated} dự án sang trạng thái Đang thực hiện.");

        return self::SUCCESS;
    }
}
