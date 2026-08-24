<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ViewAsService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn route theo role hiệu lực (`ViewAsService::effectiveRoles()`, không
 * phải $user->roles trực tiếp) — để "xem thử vai trò khác" (view-as) của
 * super_admin có tác dụng đúng trên route bọc middleware này.
 *
 * Dùng: Route::middleware(['auth', 'role:super_admin']) hoặc
 * 'role:department_director,team_lead' (thoả 1 trong nhiều role).
 */
class EnsureHasRole
{
    public function __construct(private readonly ViewAsService $viewAs) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $effective = $this->viewAs->effectiveRoles($user);

        if (empty(array_intersect($roles, $effective))) {
            abort(403, 'Bạn không có quyền truy cập khu vực này.');
        }

        return $next($request);
    }
}
