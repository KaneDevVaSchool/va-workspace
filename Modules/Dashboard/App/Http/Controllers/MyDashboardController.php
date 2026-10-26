<?php

namespace Modules\Dashboard\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Http\Requests\StoreMyProfileChangeRequest;
use Modules\Dashboard\App\Http\Requests\UpdateMyProfileAvatarRequest;
use Modules\Dashboard\App\Services\MyDashboardBirthdayGreetingService;
use Modules\Dashboard\App\Services\MyDashboardDirectoryService;
use Modules\Dashboard\App\Services\MyDashboardProfileService;
use Modules\Dashboard\App\Services\MyDashboardService;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Services\AuthenticatedUserPresenter;

/**
 * Dashboard cá nhân — LUÔN scope theo chính người đăng nhập, không nhận bất
 * kỳ tham số user/department nào từ request (khác DepartmentDashboardController
 * vốn cho phép viewer có quyền toàn cục xem phòng ban khác qua department_id).
 */
class MyDashboardController extends Controller
{
    public function __construct(
        private readonly MyDashboardService $service,
        private readonly MyDashboardDirectoryService $directory,
        private readonly MyDashboardProfileService $profile,
        private readonly MyDashboardBirthdayGreetingService $birthdayGreeting,
        private readonly AuthenticatedUserPresenter $presenter,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->service->overview($request->user()->id));
    }

    public function directory(): JsonResponse
    {
        try {
            return response()->json($this->directory->listing());
        } catch (HrmDatabaseUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function profile(Request $request): JsonResponse
    {
        try {
            return response()->json($this->profile->profile($request->user()));
        } catch (HrmDatabaseUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function birthdayGreeting(Request $request): JsonResponse
    {
        try {
            return response()->json($this->birthdayGreeting->status($request->user()));
        } catch (HrmDatabaseUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function updateProfileAvatar(UpdateMyProfileAvatarRequest $request): JsonResponse
    {
        $user = $this->profile->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json([
            'avatar_url' => $user->avatar_url,
            'user' => $this->presenter->forUser($user),
        ]);
    }

    public function storeProfileChangeRequest(StoreMyProfileChangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $record = $this->profile->storeChangeRequest(
            $request->user(),
            $validated['changes'] ?? [],
            $validated['note'] ?? null,
        );

        return response()->json([
            'request' => [
                'id' => $record->id,
                'changes' => $record->changes,
                'note' => $record->note,
                'status' => $record->status,
                'created_at' => $record->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
