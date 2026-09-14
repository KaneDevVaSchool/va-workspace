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
        $filters = $request->only(['q', 'provider_id', 'account_type', 'status', 'group']);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        $viewer = $request->user();

        $paginated = $this->service->paginate($filters, $perPage, $page, $viewer);

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
            'status_counts' => $this->service->statusCounts($viewer),
            'status_labels' => CredentialEnums::STATUS_LABELS,
            'account_type_labels' => CredentialEnums::ACCOUNT_TYPE_LABELS,
        ]);
    }

    /** Danh sách user tối giản — dùng cho chọn người được cấp quyền xem dữ liệu nhạy cảm. */
    public function users(): JsonResponse
    {
        return response()->json(['users' => $this->service->allUsers()->values()]);
    }

    /** Dự toán chi phí — tổng theo tháng/năm, theo nhà cung cấp, theo loại tài khoản, bảng chi tiết. */
    public function costSummary(Request $request): JsonResponse
    {
        return response()->json($this->service->costSummary($request->user()));
    }

    /** Dự phóng chi phí 12 tháng tới (giả định không đổi) + tháng nào có tài khoản hết hạn. */
    public function costForecast(Request $request): JsonResponse
    {
        return response()->json($this->service->costForecast($request->user()));
    }

    /** Xuất báo cáo chi phí ra file Excel (.xlsx). */
    public function exportCostExcel(Request $request)
    {
        return $this->service->exportCostExcel($request->user());
    }

    /** Xuất báo cáo chi phí ra file PDF. */
    public function exportCostPdf(Request $request)
    {
        return $this->service->exportCostPdf($request->user());
    }

    public function show(Request $request, int $credential): JsonResponse
    {
        $model = $this->service->find($credential, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        return response()->json(['credential' => $this->service->present($model, $request->user())]);
    }

    public function store(StoreCredentialRequest $request): JsonResponse
    {
        $model = $this->service->create($request->validated(), $request->user());

        return response()->json(['credential' => $this->service->present($model->fresh(['provider', 'creator', 'viewers', 'googleAccountOwner', 'department']), $request->user())], 201);
    }

    public function update(UpdateCredentialRequest $request, int $credential): JsonResponse
    {
        $model = $this->service->find($credential, $request->user());
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

        $model = $this->service->find($credential, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy tài khoản.'], 404);
        }

        $this->service->delete($model);

        return response()->json(['message' => 'Đã xoá tài khoản.']);
    }
}
