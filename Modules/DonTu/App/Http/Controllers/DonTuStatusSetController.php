<?php

namespace Modules\DonTu\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\DonTu\App\Services\DonTuStatusCatalogService;
use Modules\DonTu\App\Support\DonTuConfigAccess;

class DonTuStatusSetController extends Controller
{
    public function __construct(
        private readonly DonTuStatusCatalogService $catalog,
        private readonly DonTuConfigAccess $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canReadConfig($request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xem cấu hình đơn từ.'], 403);
        }

        return response()->json(['items' => $this->catalog->listSets()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canWriteConfig($user)) {
            return response()->json(['message' => 'Bạn không có quyền tạo nhóm trạng thái.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'copy_from_id' => ['nullable', 'integer'],
        ], [
            'name.required' => 'Nhập tên nhóm trạng thái.',
            'code.max' => 'Mã bộ trạng thái tối đa 64 ký tự.',
        ]);

        $item = $this->catalog->createSet($validated);

        return response()->json(['item' => $item], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canReadConfig($request->user())) {
            return response()->json(['message' => 'Bạn không có quyền xem cấu hình đơn từ.'], 403);
        }

        $item = $this->catalog->findSet($id);
        if ($item === null) {
            return response()->json(['message' => 'Không tìm thấy bộ trạng thái.'], 404);
        }

        return response()->json(['item' => $item]);
    }

    public function storeStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canWriteConfig($user)) {
            return response()->json(['message' => 'Bạn không có quyền thêm trạng thái.'], 403);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'group_kind' => ['required', 'in:main,conditional,terminal'],
            'terminal_kind' => ['nullable', 'in:success,failed'],
            'from_code' => ['required', 'string', 'max:64'],
            'action_key' => ['required', 'in:SAVE_DRAFT,SUBMIT,ACCEPT,APPROVE,REJECT,CANCEL,CLOSE,REQUEST_CHANGES,SUPPLEMENT,CONFIRM_PAYMENT'],
            'actor_policy' => ['required', 'in:requester,assignee,closure_actor,admin'],
        ], [
            'label.required' => 'Nhập tên trạng thái.',
            'group_kind.required' => 'Chọn chỗ của trạng thái trên đường đi.',
            'from_code.required' => 'Chọn trạng thái phía trước.',
            'action_key.required' => 'Chọn việc làm.',
            'actor_policy.required' => 'Chọn người được bấm.',
        ]);

        $item = $this->catalog->addStatus($id, $validated);

        return response()->json(['item' => $item], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Bạn cần đăng nhập.'], 401);
        }

        if (! $this->access->canWriteConfig($user)) {
            return response()->json(['message' => 'Bạn không có quyền xóa bộ trạng thái.'], 403);
        }

        $this->catalog->destroySet($id);

        return response()->json(['message' => 'Đã xóa bộ trạng thái.']);
    }
}
