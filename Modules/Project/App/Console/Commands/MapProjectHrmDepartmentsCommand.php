<?php

namespace Modules\Project\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Project\App\Services\ProjectHrmDepartmentMapper;

class MapProjectHrmDepartmentsCommand extends Command
{
    protected $signature = 'project:map-hrm-departments';

    protected $description = 'Gắn phòng ban của dự án vào phòng ban HRM của người phụ trách.';

    public function handle(ProjectHrmDepartmentMapper $mapper): int
    {
        $updated = $mapper->mapAll();

        $this->info("Đã gắn phòng ban HRM cho {$updated} dự án.");

        return self::SUCCESS;
    }
}
