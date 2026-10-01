<?php

namespace Modules\Contract\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Contract\App\Http\Requests\ChangeSupplierStatusRequest;
use Modules\Contract\App\Http\Requests\StoreSupplierRequest;
use Modules\Contract\App\Http\Requests\UpdateSupplierRequest;
use Modules\Contract\App\Models\Supplier;
use Modules\Contract\App\Services\SupplierService;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    public function options(Request $request): JsonResponse
    {
        return response()->json($this->service->options($request->user()));
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'q',
            'status',
            'supplier_type_id',
            'supplier_group_id',
            'department_id',
            'owner_user_id',
            'document_status',
            'contract_status',
        ]);
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        $paginated = $this->service->paginate($filters, $perPage, $page, $request->user());

        return response()->json([
            'suppliers' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
                'per_page' => $paginated->perPage(),
            ],
            'status_counts' => $this->service->statusCounts($filters, $request->user()),
        ]);
    }

    public function nextCode(): JsonResponse
    {
        return response()->json(['code' => $this->service->nextCode()]);
    }

    public function checkTaxCode(Request $request): JsonResponse
    {
        $duplicate = $this->service->findDuplicateTaxCode(
            $request->input('tax_code'),
            $request->filled('ignore_id') ? (int) $request->input('ignore_id') : null,
            $request->user(),
        );

        return response()->json([
            'duplicate' => $duplicate ? [
                'id' => $duplicate->id,
                'code' => $duplicate->code,
                'name' => $duplicate->name,
                'short_name' => $duplicate->short_name,
            ] : null,
        ]);
    }

    public function show(Request $request, int $supplier): JsonResponse
    {
        $model = $this->service->find($supplier, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }

        return response()->json(['supplier' => $this->service->presentDetail($model, $request->user())]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $duplicate = $this->service->findDuplicateTaxCode($request->input('tax_code'), null, $request->user());
        if ($duplicate !== null) {
            return response()->json([
                'message' => 'Mã số thuế đã thuộc nhà cung cấp khác.',
                'duplicate' => ['id' => $duplicate->id, 'code' => $duplicate->code, 'name' => $duplicate->name],
            ], 422);
        }

        $supplier = $this->service->create($request->validated(), $request->user());

        return response()->json(['supplier' => $this->service->presentDetail($supplier, $request->user())], 201);
    }

    public function update(UpdateSupplierRequest $request, int $supplier): JsonResponse
    {
        $model = $this->service->find($supplier, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }
        if (! $this->service->canManage($request->user(), $model)) {
            return response()->json(['message' => 'Bạn không có quyền sửa nhà cung cấp này.'], 403);
        }

        $duplicate = $this->service->findDuplicateTaxCode($request->input('tax_code'), $model->id, $request->user());
        if ($duplicate !== null) {
            return response()->json([
                'message' => 'Mã số thuế đã thuộc nhà cung cấp khác.',
                'duplicate' => ['id' => $duplicate->id, 'code' => $duplicate->code, 'name' => $duplicate->name],
            ], 422);
        }

        $updated = $this->service->update($model, $request->validated(), $request->user());

        return response()->json(['supplier' => $this->service->presentDetail($updated, $request->user())]);
    }

    public function destroy(Request $request, int $supplier): JsonResponse
    {
        $model = $this->service->find($supplier, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }
        if (! $this->service->canManage($request->user(), $model)) {
            return response()->json(['message' => 'Bạn không có quyền xoá nhà cung cấp này.'], 403);
        }

        try {
            $this->service->delete($model, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã xoá nhà cung cấp.']);
    }

    public function changeStatus(ChangeSupplierStatusRequest $request, int $supplier): JsonResponse
    {
        $model = $this->service->find($supplier, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }
        if (! $this->service->canManage($request->user(), $model)) {
            return response()->json(['message' => 'Bạn không có quyền đổi trạng thái nhà cung cấp này.'], 403);
        }

        $validated = $request->validated();
        $updated = $this->service->changeStatus($model, $validated['status'], $validated['reason'], $request->user());

        return response()->json(['supplier' => $this->service->presentDetail($updated, $request->user())]);
    }
}
