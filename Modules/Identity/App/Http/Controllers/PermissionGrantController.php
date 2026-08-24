<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Identity\App\Exceptions\PermissionKeyReserved;
use Modules\Identity\App\Exceptions\ScopeNotFound;
use Modules\Identity\App\Http\Requests\DestroyPermissionGrantRequest;
use Modules\Identity\App\Http\Requests\UpsertPermissionGrantRequest;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * PUT/DELETE /superadmin/api/permissions/grants — CRUD override cho
 * PermissionMatrix.vue. Chỉ gọi PermissionService (không gọi Repository
 * trực tiếp) để giữ đúng nguyên tắc 1 nơi áp luật nghiệp vụ (reserved-key
 * check, scope validation) — xem CLAUDE.md mục 5.
 */
class PermissionGrantController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

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

        // Trả về cell vừa cập nhật để frontend patch tại chỗ, không cần
        // gọi lại toàn bộ ma trận (xem PermissionMatrix.vue::onToggle()).
        $cell = $this->permissions->cellFor($data['role_code'], $data['permission_key'], $data['scope_type'], $data['scope_id'] ?? null);

        $verb = $data['granted'] ? 'Cấp' : 'Tắt';
        $this->activityLogs->record(
            $data['granted'] ? 'permission.grant' : 'permission.deny',
            "{$verb} quyền {$data['permission_key']} cho vai trò {$data['role_code']}",
            $request->user(),
            properties: [
                'role_code' => $data['role_code'],
                'permission_key' => $data['permission_key'],
                'granted' => $data['granted'],
                'scope_type' => $data['scope_type'],
            ],
        );

        return response()->json(['message' => 'Đã cập nhật quyền.', 'cell' => $cell]);
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

        $cell = $this->permissions->cellFor($data['role_code'], $data['permission_key'], $data['scope_type'], $data['scope_id'] ?? null);

        $this->activityLogs->record(
            'permission.revoke',
            "Khôi phục quyền mặc định {$data['permission_key']} của vai trò {$data['role_code']}",
            $request->user(),
            properties: [
                'role_code' => $data['role_code'],
                'permission_key' => $data['permission_key'],
                'scope_type' => $data['scope_type'],
            ],
        );

        return response()->json(['message' => 'Đã khôi phục mặc định.', 'cell' => $cell]);
    }
}
