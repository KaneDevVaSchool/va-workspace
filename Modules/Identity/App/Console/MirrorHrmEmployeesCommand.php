<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;

class MirrorHrmEmployeesCommand extends Command
{
    protected $signature = 'identity:hrm-mirror-employees
                            {--force : Bỏ cache TTL và mirror lại toàn bộ}';

    protected $description = 'Mirror nhân sự từ MySQL VA-HRM sang bảng users workspace (HRM_DB_*)';

    public function handle(HrmEmployeeDatabaseMirrorService $mirror): int
    {
        if (! HrmEmployeeDatabaseMirrorService::isConfigured()) {
            $this->error('Chưa cấu hình HRM_DB_* — không mirror được.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            Cache::forget('hrm:employee_db_mirror:done');
        }

        $this->info('Đang mirror nhân sự từ DB HRM…');
        $mirror->mirrorAll();
        Cache::put('hrm:employee_db_mirror:done', true, max(60, (int) config('services.hrm.employee_bulk_sync_ttl', 900)));
        $this->info('Xong.');

        return self::SUCCESS;
    }
}
