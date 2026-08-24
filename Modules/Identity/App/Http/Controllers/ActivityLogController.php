<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Identity\App\Http\Requests\ActivityLogExportRequest;
use Modules\Identity\App\Http\Requests\ActivityLogIndexRequest;
use Modules\Identity\App\Services\ActivityLogService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function recent(): JsonResponse
    {
        $logs = $this->activityLogs->presentMany($this->activityLogs->recent(20));

        return response()->json(['logs' => $logs]);
    }

    public function options(): JsonResponse
    {
        return response()->json($this->activityLogs->filterOptions());
    }

    public function index(ActivityLogIndexRequest $request): JsonResponse
    {
        $paginator = $this->activityLogs->paginate(
            $request->filters(),
            $request->perPage(),
        );

        return response()->json([
            'logs' => $this->activityLogs->presentMany($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function export(ActivityLogExportRequest $request): BinaryFileResponse
    {
        return $this->activityLogs->export(
            $request->filters(),
            $request->exportKind(),
            $request->user(),
        );
    }
}
