<?php

namespace Modules\DonTu\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\DonTu\App\Models\DonTuWorkflow;
use Modules\DonTu\App\Services\DonTuWorkflowService;
use Modules\DonTu\App\Support\DonTuConfigAccess;

class DonTuWorkflowController extends Controller
{
    public function __construct(
        private readonly DonTuWorkflowService $service,
        private readonly DonTuConfigAccess $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canReadConfig($user)) {
            return response()->json(['message' => 'Bạn không có quyền xem quy trình.'], 403);
        }

        return response()->json([
            'items' => $this->service->listForUser($user, $request->integer('department_id') ?: null),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canReadConfig($user)) {
            return response()->json(['message' => 'Bạn không có quyền xem quy trình.'], 403);
        }

        $workflow = $this->service->find($id);
        if ($workflow === null) {
            return response()->json(['message' => 'Không tìm thấy quy trình.'], 404);
        }

        return response()->json(['item' => $this->service->presentDetail($workflow)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $deptId = $request->input('department_id', $user->department_id);
        if (! $this->access->canWriteConfig($user, $deptId ? (int) $deptId : null)) {
            return response()->json(['message' => 'Bạn không có quyền tạo quy trình.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'status_set_id' => ['required', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'is_financial' => ['sometimes', 'boolean'],
            'initiator_policy' => ['nullable', 'in:staff,parent,both'],
            'closure_actor_policy' => ['nullable', 'string', 'max:32'],
            'closure_role' => ['nullable', 'string', 'max:64'],
        ]);

        $item = $this->service->create($user, $validated);

        return response()->json(['item' => $this->service->presentDetail($item->load(['statusSet', 'forms', 'steps']))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $workflow = $this->service->find($id);
        if ($workflow === null) {
            return response()->json(['message' => 'Không tìm thấy quy trình.'], 404);
        }

        if (! $this->canMutateWorkflow($user, $workflow)) {
            return response()->json(['message' => 'Bạn không có quyền sửa quy trình này.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_financial' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'initiator_policy' => ['sometimes', 'in:staff,parent,both'],
            'closure_actor_policy' => ['sometimes', 'string', 'max:32'],
            'closure_role' => ['nullable', 'string', 'max:64'],
            'forms' => ['sometimes', 'array'],
            'steps' => ['sometimes', 'array'],
        ]);

        if (isset($validated['forms'])) {
            $workflow = $this->service->syncForms($workflow, $validated['forms']);
            unset($validated['forms']);
        }
        if (isset($validated['steps'])) {
            $workflow = $this->service->syncSteps($workflow, $validated['steps']);
            unset($validated['steps']);
        }

        if ($validated !== []) {
            $workflow = $this->service->updateMeta($workflow, $validated);
        }

        return response()->json(['item' => $this->service->presentDetail($workflow)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $workflow = $this->service->find($id);
        if ($workflow === null) {
            return response()->json(['message' => 'Không tìm thấy quy trình.'], 404);
        }

        if (! $this->canMutateWorkflow($user, $workflow)) {
            return response()->json(['message' => 'Bạn không có quyền xóa quy trình này.'], 403);
        }

        $this->service->destroy($workflow);

        return response()->json(['message' => 'Đã xóa quy trình.']);
    }

    private function canMutateWorkflow($user, DonTuWorkflow $workflow): bool
    {
        if ($workflow->department_id === null) {
            return $this->access->canWriteSystemScope($user);
        }

        return $this->access->canWriteConfig($user, (int) $workflow->department_id);
    }
}
