<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Identity\App\Exceptions\NotSuperAdmin;
use Modules\Identity\App\Exceptions\RoleNotFound;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Services\AuthenticatedUserPresenter;
use Modules\Identity\App\Services\ViewAsService;

/**
 * POST /api/view-as, DELETE /api/view-as — "xem thử" vai trò khác.
 */
class ViewAsController extends Controller
{
    public function __construct(
        private readonly ViewAsService $viewAs,
        private readonly AuthenticatedUserPresenter $presenter,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_code' => [
                'required',
                'string',
                Rule::in(Role::query()->pluck('code')->all()),
            ],
        ]);

        try {
            $this->viewAs->activate($request->user(), $data['role_code']);
        } catch (NotSuperAdmin $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (RoleNotFound $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $request->session()->save();

        return response()->json([
            'user' => $this->presenter->forUser($request->user()),
        ]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $this->viewAs->deactivate();
        $request->session()->save();

        return response()->json([
            'user' => $this->presenter->forUser($request->user()),
        ]);
    }
}
