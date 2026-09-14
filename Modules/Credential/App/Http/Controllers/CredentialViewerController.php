<?php

namespace Modules\Credential\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Credential\App\Http\Requests\StoreCredentialViewerRequest;
use Modules\Credential\App\Http\Requests\SyncCredentialViewersRequest;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Services\CredentialService;

/**
 * Thêm/xoá người được người tạo cấp quyền xem dữ liệu nhạy cảm của 1
 * credential cụ thể (bảng credential_viewers) — tách biệt hoàn toàn với
 * quyền CRUD (permission:credential.manage). Chỉ creator (hoặc người có
 * credential.manage) được thao tác ở đây.
 */
class CredentialViewerController extends Controller
{
    public function __construct(
        private readonly CredentialService $service,
    ) {}

    public function store(StoreCredentialViewerRequest $request, int $credential): JsonResponse
    {
        $model = $this->service->find($credential, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        if (! $this->canManageViewers($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền cấp quyền xem cho tài khoản này.'], 403);
        }

        $this->service->addViewer($model, (int) $request->validated()['user_id'], $request->user());

        return response()->json(['credential' => $this->service->present($model->fresh(['provider', 'creator', 'viewers', 'department']), $request->user())], 201);
    }

    public function destroy(Request $request, int $credential, int $user): JsonResponse
    {
        $model = $this->service->find($credential, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        if (! $this->canManageViewers($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền thu hồi quyền xem của tài khoản này.'], 403);
        }

        $this->service->removeViewer($model, $user);

        return response()->json(['credential' => $this->service->present($model->fresh(['provider', 'creator', 'viewers', 'department']), $request->user())]);
    }

    /**
     * Đồng bộ toàn bộ danh sách viewer trong 1 lần (picker multi-select ở
     * CredentialDetail.vue) — thay cho gọi store/destroy tuần tự từng
     * người. Giữ nguyên store()/destroy() ở trên (không breaking change).
     */
    public function sync(SyncCredentialViewersRequest $request, int $credential): JsonResponse
    {
        $model = $this->service->find($credential, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        if (! $this->canManageViewers($model, $request->user())) {
            return response()->json(['message' => 'Bạn không có quyền cấp quyền xem cho tài khoản này.'], 403);
        }

        $this->service->syncViewers($model, $request->validated()['user_ids'], $request->user());

        return response()->json(['credential' => $this->service->present($model->fresh(['provider', 'creator', 'viewers', 'department']), $request->user())]);
    }

    private function canManageViewers(Credential $credential, User $viewer): bool
    {
        return $credential->created_by === $viewer->id || $this->service->canManage($viewer);
    }
}
