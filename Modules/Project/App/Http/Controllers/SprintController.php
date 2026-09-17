<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Project\App\Http\Requests\StoreSprintRequest;
use Modules\Project\App\Http\Requests\UpdateSprintRequest;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Repositories\Contracts\SprintRepositoryInterface;
use Modules\Project\App\Services\SprintService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class SprintController extends Controller
{
    public function __construct(
        private readonly SprintService $service,
        private readonly SprintRepositoryInterface $sprints,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $phaseId = $request->integer('phase_id') ?: null;
        $items = $this->service->listForProject($project);

        if ($phaseId !== null) {
            $items = array_values(array_filter($items, fn (array $item) => $item['phase_id'] === $phaseId));
        }

        return response()->json(['sprints' => $items]);
    }

    public function store(StoreSprintRequest $request, Project $project): JsonResponse
    {
        $result = $this->service->create($project, $request->validated(), $request->user());
        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        $this->activityLogs->record(
            'project_sprint.create',
            "Tạo sprint \"{$result->name}\" cho dự án \"{$project->name}\"",
            $request->user(),
            'project',
            $project->id,
        );

        return response()->json(['sprint' => $this->service->present($result)], 201);
    }

    public function update(UpdateSprintRequest $request, int $sprint): JsonResponse
    {
        $model = $this->sprints->find($sprint);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy sprint.'], 404);
        }

        $result = $this->service->update($model, $request->validated(), $request->user());
        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        $this->activityLogs->record(
            'project_sprint.update',
            "Cập nhật sprint \"{$result->name}\"",
            $request->user(),
            'project',
            $result->project_id,
        );

        return response()->json(['sprint' => $this->service->present($result)]);
    }

    public function destroy(Request $request, int $sprint): JsonResponse
    {
        $model = $this->sprints->find($sprint);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy sprint.'], 404);
        }

        $name = $model->name;
        $projectId = $model->project_id;
        $result = $this->service->delete($model);
        if (is_array($result)) {
            return response()->json(['message' => $result['error']], 422);
        }

        $this->activityLogs->record(
            'project_sprint.delete',
            "Xoá sprint \"{$name}\"",
            $request->user(),
            'project',
            $projectId,
        );

        return response()->json(['message' => 'Đã xoá sprint.']);
    }
}
