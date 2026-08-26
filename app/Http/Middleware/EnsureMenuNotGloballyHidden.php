<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuVisibilityRepositoryInterface;
use Modules\Identity\App\Services\ViewAsService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn truy cập route ứng với 1 menu_key đã bị superadmin ẩn Ở MỨC TOÀN
 * HỆ THỐNG (xem Modules/WorkspaceConfig/App/Services/GlobalMenuVisibilityService.php).
 *
 * Cú pháp trên Route:
 *   Route::middleware('menu.not_hidden:manager.social.moderation')
 *
 * super_admin thực sự (không đang xem thử vai trò khác) luôn bỏ qua —
 * đối xứng với PermissionService::allows() và AppSidebar.vue::itemPasses().
 */
class EnsureMenuNotGloballyHidden
{
    public function __construct(
        private readonly GlobalMenuVisibilityRepositoryInterface $globalMenus,
        private readonly ViewAsService $viewAs,
    ) {}

    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $this->viewAs->isImpersonating() && $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($this->globalMenus->isHidden($menuKey)) {
            abort(403, 'Mục này đã bị ẩn theo cấu hình hệ thống.');
        }

        return $next($request);
    }
}
