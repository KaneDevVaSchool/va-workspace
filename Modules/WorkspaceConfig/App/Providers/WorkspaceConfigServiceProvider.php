<?php

namespace Modules\WorkspaceConfig\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Module WorkspaceConfig — hub cấu hình scoped theo phòng ban (thành viên,
 * bật/tắt menu sidebar, tiêu chí đánh giá). Controller/Service của module
 * này gọi lại các Repository interface đã bind sẵn ở IdentityServiceProvider
 * (UserRepositoryInterface, DepartmentSidebarConfigRepositoryInterface) —
 * không cần bind riêng gì trong module này.
 */
class WorkspaceConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), KHÔNG trùng
     * path trang Vue /manager/workspace-config/* và /superadmin/workspace-config/*.
     * Cùng kiểu Identity: trang /superadmin/activity, API /api/activity-logs.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('WorkspaceConfig', 'routes');

        if (file_exists($basePath . '/manager.php')) {
            Route::middleware('web')->prefix('api')->name('api.manager.')->group($basePath . '/manager.php');
        }

        if (file_exists($basePath . '/superadmin.php')) {
            Route::middleware('web')->prefix('api')->name('api.superadmin.')->group($basePath . '/superadmin.php');
        }
    }
}
