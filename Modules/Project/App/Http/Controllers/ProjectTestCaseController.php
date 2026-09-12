<?php

namespace Modules\Project\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Project\App\Http\Requests\StoreProjectTestCaseRequest;
use Modules\Project\App\Http\Requests\UpdateProjectTestCaseCheckRequest;
use Modules\Project\App\Http\Requests\UpdateProjectTestCaseRequest;
use Modules\Project\App\Http\Requests\UploadProjectTestCaseAttachmentRequest;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Repositories\Contracts\ProjectTestCaseRepositoryInterface;
use Modules\Project\App\Services\ProjectTestCaseService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response.
 */
class ProjectTestCaseController extends Controller
{
    public function __construct(
        private readonly ProjectTestCaseService $service,
        private readonly ProjectTestCaseRepositoryInterface $testCases,
    ) {}

    public function index(Project $project): JsonResponse
    {
        return response()->json(['test_cases' => $this->service->listForProject($project)]);
    }

    public function store(StoreProjectTestCaseRequest $request, Project $project): JsonResponse
    {
        $testCase = $this->service->create($project, $request->validated(), $request->user());

        return response()->json(['test_case' => $this->service->present($testCase)], 201);
    }

    public function update(UpdateProjectTestCaseRequest $request, int $testCase): JsonResponse
    {
        $model = $this->testCases->find($testCase);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy testcase.'], 404);
        }

        $updated = $this->service->update($model, $request->validated(), $request->user());

        return response()->json(['test_case' => $this->service->present($updated)]);
    }

    public function destroy(Request $request, int $testCase): JsonResponse
    {
        $model = $this->testCases->find($testCase);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy testcase.'], 404);
        }

        $this->service->delete($model);

        return response()->json(['message' => 'Đã xoá testcase.']);
    }

    /** Chỉ assignee của chính testcase mới được chấm Check lần 1. */
    public function updateCheck1(UpdateProjectTestCaseCheckRequest $request, int $testCase): JsonResponse
    {
        $model = $this->testCases->find($testCase);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy testcase.'], 404);
        }

        if (! $this->service->canCheck1($model, $request->user())) {
            return response()->json(['message' => 'Chỉ người thực hiện mới được chấm Check lần 1.'], 403);
        }

        $updated = $this->service->updateCheck1($model, $request->validated()['status'], $request->user());

        return response()->json(['test_case' => $this->service->present($updated)]);
    }

    /** Chỉ người tạo testcase mới được chấm Check lần 2. */
    public function updateCheck2(UpdateProjectTestCaseCheckRequest $request, int $testCase): JsonResponse
    {
        $model = $this->testCases->find($testCase);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy testcase.'], 404);
        }

        if (! $this->service->canCheck2($model, $request->user())) {
            return response()->json(['message' => 'Chỉ người tạo testcase mới được chấm Check lần 2.'], 403);
        }

        $updated = $this->service->updateCheck2($model, $request->validated()['status'], $request->user());

        return response()->json(['test_case' => $this->service->present($updated)]);
    }

    public function uploadAttachment(UploadProjectTestCaseAttachmentRequest $request, int $testCase): JsonResponse
    {
        $model = $this->testCases->find($testCase);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy testcase.'], 404);
        }

        $updated = $this->service->updateAttachment($model, $request->file('attachment'), $request->user());

        return response()->json(['test_case' => $this->service->present($updated)]);
    }

    public function destroyAttachment(Request $request, int $testCase): JsonResponse
    {
        $model = $this->testCases->find($testCase);
        if (! $model) {
            return response()->json(['message' => 'Không tìm thấy testcase.'], 404);
        }

        $updated = $this->service->deleteAttachment($model, $request->user());

        return response()->json(['test_case' => $this->service->present($updated)]);
    }
}
