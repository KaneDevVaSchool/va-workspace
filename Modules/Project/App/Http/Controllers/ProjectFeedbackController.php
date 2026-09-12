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

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class ProjectFeedbackController extends Controller
{
    public function __construct(
        private readonly ProjectFeedbackService $service,
        private readonly ProjectFeedbackRepositoryInterface $feedbacks,
    ) {}

    public function index(Project $project): JsonResponse
    {
        return response()->json(['feedbacks' => $this->service->listForProject($project)]);
    }

    public function store(StoreProjectFeedbackRequest $request, Project $project): JsonResponse
    {
        $feedback = $this->service->create($project, $request->validated(), $request->user());

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

        $this->service->delete($model);

        return response()->json(['message' => 'Đã xoá phản hồi.']);
    }
}
