<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\AuthenticatedUserPresenter;

/**
 * GET /api/me — SPA (middleware web + prefix api). Cùng session với view-as.
 */
class MeController extends Controller
{
    public function __construct(
        private readonly AuthenticatedUserPresenter $presenter,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->presenter->forUser($request->user()));
    }

    public function csrf(): JsonResponse
    {
        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }
}
