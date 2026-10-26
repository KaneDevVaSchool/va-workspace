<?php

namespace Modules\DonTu\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\DonTu\App\Models\DonTuFormTemplate;
use Modules\DonTu\App\Services\DonTuFormTemplateService;
use Modules\DonTu\App\Support\DonTuConfigAccess;

class DonTuFormTemplateController extends Controller
{
    public function __construct(
        private readonly DonTuFormTemplateService $service,
        private readonly DonTuConfigAccess $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canReadConfig($user)) {
            return response()->json(['message' => 'Bạn không có quyền xem mẫu biểu.'], 403);
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
            return response()->json(['message' => 'Bạn không có quyền xem mẫu biểu.'], 403);
        }

        $template = $this->service->find($id);
        if ($template === null) {
            return response()->json(['message' => 'Không tìm thấy mẫu biểu.'], 404);
        }

        return response()->json(['item' => $this->service->present($template)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $deptId = $request->input('department_id', $user->department_id);
        if (! $this->access->canWriteConfig($user, $deptId ? (int) $deptId : null)) {
            return response()->json(['message' => 'Bạn không có quyền tạo mẫu biểu.'], 403);
        }

        if ($deptId === null && ! $this->access->canWriteSystemScope($user)) {
            return response()->json(['message' => 'Chỉ quản trị hệ thống mới tạo mẫu toàn trường.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', 'in:internal,external,both'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $item = $this->service->create($user, $validated);

        return response()->json(['item' => $this->service->present($item)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $template = $this->service->find($id);
        if ($template === null) {
            return response()->json(['message' => 'Không tìm thấy mẫu biểu.'], 404);
        }

        if (! $this->canMutateTemplate($user, $template)) {
            return response()->json(['message' => 'Bạn không có quyền sửa mẫu này.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope' => ['sometimes', 'in:internal,external,both'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'schema_json' => ['sometimes', 'array'],
            'branding_json' => ['nullable', 'array'],
        ]);

        $item = $this->service->update($template, $user, $validated);

        return response()->json(['item' => $this->service->present($item)]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $template = $this->service->find($id);
        if ($template === null) {
            return response()->json(['message' => 'Không tìm thấy mẫu biểu.'], 404);
        }

        if (! $this->canMutateTemplate($user, $template, writeOnly: false)) {
            return response()->json(['message' => 'Bạn không có quyền nhân bản mẫu này.'], 403);
        }

        $copy = $this->service->duplicate($template, $user);

        return response()->json(['item' => $this->service->present($copy)], 201);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $template = $this->service->find($id);
        if ($template === null) {
            return response()->json(['message' => 'Không tìm thấy mẫu biểu.'], 404);
        }

        if (! $this->canMutateTemplate($user, $template)) {
            return response()->json(['message' => 'Bạn không có quyền ẩn mẫu này.'], 403);
        }

        $this->service->archive($template);

        return response()->json(['item' => $this->service->present($template->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        $template = $this->service->find($id);
        if ($template === null) {
            return response()->json(['message' => 'Không tìm thấy mẫu biểu.'], 404);
        }

        if (! $this->canMutateTemplate($user, $template)) {
            return response()->json(['message' => 'Bạn không có quyền xóa mẫu này.'], 403);
        }

        $this->service->destroy($template);

        return response()->json(['message' => 'Đã xóa mẫu biểu.']);
    }

    private function canMutateTemplate($user, DonTuFormTemplate $template, bool $writeOnly = true): bool
    {
        if ($template->department_id === null) {
            return $this->access->canWriteSystemScope($user);
        }

        return $this->access->canWriteConfig($user, (int) $template->department_id);
    }
}
