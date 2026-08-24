<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ViewAsService;

/**
 * GET /api/me — trả user hiện tại cho SPA (gọi sau khi Google redirect về
 * AuthCallback.vue). 401 nếu chưa đăng nhập (middleware auth:sanctum).
 */
class MeController extends Controller
{
    public function __construct(private readonly ViewAsService $viewAs) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['department', 'roles']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'code' => $user->department->code,
                'name' => $user->department->name,
            ] : null,
            // RBAC tối giản (Modules/Identity) — roles = vai trò thật được
            // gán; active_role = vai trò hiệu lực (ưu tiên view-as override
            // nếu super_admin đang "xem thử"); xem ViewAsService.
            'roles' => $user->roles->pluck('code'),
            'active_role' => $this->viewAs->effectiveRoles($user)[0] ?? null,
            'is_impersonating' => $this->viewAs->isImpersonating(),
        ]);
    }
}
