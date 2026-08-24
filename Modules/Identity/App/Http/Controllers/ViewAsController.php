<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Exceptions\NotSuperAdmin;
use Modules\Identity\App\Exceptions\RoleNotFound;
use Modules\Identity\App\Services\ViewAsService;

/**
 * POST /api/view-as, DELETE /api/view-as — "xem thử" vai trò khác, chỉ
 * super_admin. Controller mỏng: không tự check quyền, ViewAsService ném
 * exception khi vi phạm.
 */
class ViewAsController extends Controller
{
    public function __construct(private readonly ViewAsService $viewAs) {}

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_code' => ['required', 'string'],
        ]);

        try {
            $this->viewAs->activate($request->user(), $data['role_code']);
        } catch (NotSuperAdmin $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (RoleNotFound $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['active_role' => $data['role_code']]);
    }

    public function deactivate(): JsonResponse
    {
        $this->viewAs->deactivate();

        return response()->json(['active_role' => null]);
    }
}
