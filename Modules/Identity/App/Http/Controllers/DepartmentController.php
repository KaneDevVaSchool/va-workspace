<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;

/**
 * Danh sách phòng ban — dùng cho dropdown chọn scope (permission matrix,
 * quản lý team). Chưa có endpoint nào trước đó, tạo mới tối thiểu.
 */
class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentRepositoryInterface $departments) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'departments' => $this->departments->allActive()
                ->map(fn (Department $d) => [
                    'id' => $d->id,
                    'code' => $d->code,
                    'name' => $d->name,
                ])
                ->values(),
        ]);
    }
}
