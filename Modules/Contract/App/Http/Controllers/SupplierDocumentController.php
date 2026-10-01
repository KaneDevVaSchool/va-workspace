<?php

namespace Modules\Contract\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Contract\App\Http\Requests\ReviewSupplierDocumentRequest;
use Modules\Contract\App\Http\Requests\UploadSupplierDocumentRequest;
use Modules\Contract\App\Models\SupplierDocument;
use Modules\Contract\App\Services\SupplierService;

class SupplierDocumentController extends Controller
{
    public function __construct(private readonly SupplierService $service) {}

    public function store(UploadSupplierDocumentRequest $request, int $supplier): JsonResponse
    {
        $model = $this->service->find($supplier, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }
        if (! $this->service->canManage($request->user(), $model)) {
            return response()->json(['message' => 'Bạn không có quyền tải hồ sơ cho nhà cung cấp này.'], 403);
        }

        $document = $this->service->uploadDocument($model, $request->validated(), $request->user());
        $fresh = $this->service->find($supplier, $request->user());

        return response()->json([
            'document' => $document,
            'supplier' => $fresh ? $this->service->presentDetail($fresh, $request->user()) : null,
        ], 201);
    }

    public function review(ReviewSupplierDocumentRequest $request, int $supplier, int $document): JsonResponse
    {
        $model = $this->service->find($supplier, $request->user());
        if ($model === null) {
            return response()->json(['message' => 'Không tìm thấy nhà cung cấp.'], 404);
        }
        if (! $this->service->canManage($request->user(), $model)) {
            return response()->json(['message' => 'Bạn không có quyền kiểm tra hồ sơ nhà cung cấp này.'], 403);
        }

        $doc = SupplierDocument::query()
            ->where('supplier_id', $model->id)
            ->with('supplier')
            ->find($document);

        if ($doc === null) {
            return response()->json(['message' => 'Không tìm thấy hồ sơ.'], 404);
        }

        $reviewed = $this->service->reviewDocument($doc, $request->validated(), $request->user());
        $fresh = $this->service->find($supplier, $request->user());

        return response()->json([
            'document' => $reviewed,
            'supplier' => $fresh ? $this->service->presentDetail($fresh, $request->user()) : null,
        ]);
    }
}
