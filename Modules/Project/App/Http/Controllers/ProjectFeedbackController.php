<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Project\App\Http\Requests\StoreProjectFeedbackRequest;
use Modules\Project\App\Http\Requests\UpdateProjectFeedbackRequest;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Repositories\Contracts\ProjectFeedbackRepositoryInterface;
use Modules\Project\App\Services\ProjectFeedbackService;
use Modules\Identity\App\Services\ActivityLogService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class ProjectFeedbackController extends Controller
{
    public function __construct(
        private readonly ProjectFeedbackService $service,
        private readonly ProjectFeedbackRepositoryInterface $feedbacks,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Project $project): JsonResponse
    {
        return response()->json(['feedbacks' => $this->service->listForProject($project)]);
    }

    public function store(StoreProjectFeedbackRequest $request, Project $project): JsonResponse
    {
        $feedback = $this->service->create($project, $request->validated(), $request->user());

        $this->activityLogs->record(
            'project_feedback.create',
            "Thêm phản hồi cho dự án \"{$project->name}\"",
            $request->user(),
            'project',
            $project->id,
        );

        return response()->json(['feedback' => $this->service->present($feedback)], 201);
    }

    public function update(UpdateProjectFeedbackRequest $request, int $feedback): JsonResponse
    {
        $model = $this->feedbacks->find($feedback);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy phản hồi.'], 404);
        }

        if (! $this->service->canEdit($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền sửa phản hồi này.'], 403);
        }

        $updated = $this->service->update($model, $request->validated());

        $this->activityLogs->record(
            'project_feedback.update',
            "Cập nhật phản hồi của dự án \"{$updated->project->name}\"",
            $request->user(),
            'project',
            $updated->project_id,
        );

        return response()->json(['feedback' => $this->service->present($updated)]);
    }

    public function destroy(Request $request, int $feedback): JsonResponse
    {
        $model = $this->feedbacks->find($feedback);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy phản hồi.'], 404);
        }

        if (! $this->service->canEdit($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xoá phản hồi này.'], 403);
        }

        $projectName = $model->project->name;
        $projectId = $model->project_id;
        $this->service->delete($model);

        $this->activityLogs->record(
            'project_feedback.delete',
            "Xoá phản hồi của dự án \"{$projectName}\"",
            $request->user(),
            'project',
            $projectId,
        );

        return response()->json(['message' => 'Đã xoá phản hồi.']);
    }
}
