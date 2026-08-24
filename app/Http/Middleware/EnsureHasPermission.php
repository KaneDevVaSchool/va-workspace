<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\PermissionService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware kiểm tra quyền granular (PermissionService::allows).
 *
 * Cú pháp trên Route:
 *   Route::middleware('permission:task.delegate')
 *   Route::middleware('permission:project.create,department')
 *   Route::middleware('permission:project.create,department,{dept_id}')
 *
 * Tham số:
 *   $key       — permission key, vd. 'task.delegate'
 *   $scopeType — 'global' | 'department' | 'team' (mặc định 'global')
 *   $scopeIdParam — tên route parameter chứa scope ID, vd. 'department'
 *                   middleware tự lấy $request->route($scopeIdParam) nếu có.
 *
 * Kết hợp với EnsureHasRole cho route cần cả role lẫn permission cụ thể:
 *   Route::middleware(['auth', 'role:department_director', 'permission:project.create,department'])
 */
class EnsureHasPermission
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(
        Request $request,
        Closure $next,
        string $key,
        string $scopeType = 'global',
        string $scopeIdParam = '',
    ): Response {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $scopeId = null;
        if ($scopeIdParam !== '' && $scopeType !== 'global') {
            $raw = $request->route($scopeIdParam);
            $scopeId = $raw !== null ? (int) $raw : null;
        }

        if (! $this->permissions->allows($user, $key, $scopeType, $scopeId)) {
            abort(403, "Bạn không có quyền thực hiện hành động này ({$key}).");
        }

        return $next($request);
    }
}
