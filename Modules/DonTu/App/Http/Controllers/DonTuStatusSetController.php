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
}
