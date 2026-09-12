<?php

namespace Modules\Credential\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Credential\App\Http\Requests\StoreCredentialRequest;
use Modules\Credential\App\Http\Requests\UpdateCredentialRequest;
use Modules\Credential\App\Services\CredentialService;

/**
 * Controller mỏng: chỉ nhận request, gọi Service, trả response. Không chứa
 * business logic hay truy vấn DB — xem CredentialService/CredentialRepository.
 */
class CredentialController extends Controller
{
    public function __construct(
        private readonly CredentialService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['q', 'provider_id', 'account_type', 'status']);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        $viewer = $request->user();

        $paginated = $this->service->paginate($filters, $perPage, $page);

        return response()->json([
            'credentials' => collect($paginated->items())->map(fn ($c) => $this->service->present($c, $viewer))->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
                'per_page' => $paginated->perPage(),
            ],
            'status_counts' => $this->service->statusCounts(),
            'status_labels' => CredentialEnums::STATUS_LABELS,
            'account_type_labels' => CredentialEnums::ACCOUNT_TYPE_LABELS,
        ]);
    }

    /** Danh sách user tối giản — dùng cho chọn người được cấp quyền xem dữ liệu nhạy cảm. */
    public function users(): JsonResponse
    {
        return response()->json(['users' => $this->service->allUsers()->values()]);
    }

    public function show(Request $request, int $credential): JsonResponse
    {
        $model = $this->service->find($credential);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        return response()->json(['credential' => $this->service->present($model, $request->user())]);
    }

    public function store(StoreCredentialRequest $request): JsonResponse
    {
        $model = $this->service->create($request->validated(), $request->user());

        return response()->json(['credential' => $this->service->present($model->fresh(['provider', 'creator', 'viewers']), $request->user())], 201);
    }

    public function update(UpdateCredentialRequest $request, int $credential): JsonResponse
    {
        $model = $this->service->find($credential);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        $updated = $this->service->update($model, $request->validated(), $request->user());

        return response()->json(['credential' => $this->service->present($updated, $request->user())]);
    }

    public function destroy(Request $request, int $credential): JsonResponse
    {
        if (! $request->user()->allows('credential.manage')) {
            return response()->json(['message' => 'Bạn không có quyền xoá tài khoản.'], 403);
        }

        $model = $this->service->find($credential);
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        $this->service->delete($model);

        return response()->json(['message' => 'Đã xoá tài khoản.']);
    }
}
