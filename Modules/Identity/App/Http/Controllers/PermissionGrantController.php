<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Identity\App\Exceptions\PermissionKeyReserved;
use Modules\Identity\App\Exceptions\ScopeNotFound;
use Modules\Identity\App\Http\Requests\DestroyPermissionGrantRequest;
use Modules\Identity\App\Http\Requests\UpsertPermissionGrantRequest;
use Modules\Identity\App\Services\PermissionService;

/**
 * PUT/DELETE /superadmin/api/permissions/grants — CRUD override cho
 * PermissionMatrix.vue. Chỉ gọi PermissionService (không gọi Repository
 * trực tiếp) để giữ đúng nguyên tắc 1 nơi áp luật nghiệp vụ (reserved-key
 * check, scope validation) — xem CLAUDE.md mục 5.
 */
class PermissionGrantController extends Controller
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function upsert(UpsertPermissionGrantRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->permissions->setGrant(
                $data['role_code'],
                $data['permission_key'],
                (bool) $data['granted'],
                $data['scope_type'],
                $data['scope_id'] ?? null,
                $request->user()->id,
            );
        } catch (PermissionKeyReserved $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ScopeNotFound $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã cập nhật quyền.']);
    }

    public function destroy(DestroyPermissionGrantRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $this->permissions->revokeGrant(
                $data['role_code'],
                $data['permission_key'],
                $data['scope_type'],
                $data['scope_id'] ?? null,
            );
        } catch (PermissionKeyReserved $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (ScopeNotFound $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Đã khôi phục mặc định.']);
    }
}
