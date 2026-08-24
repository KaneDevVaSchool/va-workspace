<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Services\ActivityLogService;

class ActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function recent(): JsonResponse
    {
        $logs = $this->activityLogs->recent(20)
            ->map(fn (ActivityLog $log) => $this->activityLogs->present($log))
            ->values();

        return response()->json(['logs' => $logs]);
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->activityLogs->paginate(
            [
                'q' => (string) $request->query('q', ''),
                'action' => (string) $request->query('action', ''),
            ],
            20,
        );

        return response()->json([
            'logs' => collect($paginator->items())
                ->map(fn (ActivityLog $log) => $this->activityLogs->present($log))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
