<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Services\PermissionService;

/**
 * GET /superadmin/api/permissions/matrix — dữ liệu cho trang
 * Ma trận phân quyền. Backend là single source of truth: trả đủ
 * roles/modules/permissions/matrix, frontend không tự suy luận.
 */
class PermissionMatrixController extends Controller
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function matrix(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope_type' => ['sometimes', Rule::in(['global', 'department', 'team'])],
            'scope_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $scopeType = $data['scope_type'] ?? 'global';
        $scopeId = $scopeType === 'global' ? null : ($data['scope_id'] ?? null);

        $roles = Role::query()
            ->where('code', '!=', 'super_admin')
            ->orderBy('id')
            ->get(['code', 'name'])
            ->map(fn (Role $role) => ['code' => $role->code, 'label' => $role->name])
            ->values();

        $catalog = config('permissions.catalog', []);

        $modules = [];
        $permissions = [];
        foreach ($catalog as $key => $meta) {
            $moduleLabel = $meta['module'] ?? $key;
            if (! isset($modules[$moduleLabel])) {
                $modules[$moduleLabel] = ['key' => $moduleLabel, 'label' => $moduleLabel];
            }

            $permissions[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'description' => $meta['description'] ?? '',
                'module' => $moduleLabel,
                'reserved' => $this->permissions->isReserved($key),
            ];
        }

        return response()->json([
            'roles' => $roles,
            'modules' => array_values($modules),
            'permissions' => $permissions,
            'scope' => ['type' => $scopeType, 'id' => $scopeId],
            'matrix' => $this->permissions->matrixFor($scopeType, $scopeId),
        ]);
    }
}
