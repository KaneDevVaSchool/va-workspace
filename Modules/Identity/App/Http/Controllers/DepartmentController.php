<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;

/**
 * Danh sách phòng ban — dùng cho dropdown chọn scope (permission matrix,
 * quản lý team). Chưa có endpoint nào trước đó, tạo mới tối thiểu.
 */
class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
        private readonly HrmDepartmentSyncService $hrmDepartmentSync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $source = (string) $request->query('source', '');

        if ($source === 'hrm' && HrmDepartmentSyncService::isConfigured()) {
            $this->hrmDepartmentSync->syncDepartmentsFromHrm();
            $rows = $this->departments->allActiveSyncedFromHrmForPicker();
        } else {
            $rows = $this->departments->allActive();
        }

        return response()->json([
            'departments' => $rows
                ->map(fn (Department $d) => [
                    'id' => $d->id,
                    'code' => $d->code,
                    'name' => $d->name,
                    'hrm_code' => $d->external_code,
                ])
                ->values(),
        ]);
    }
}
