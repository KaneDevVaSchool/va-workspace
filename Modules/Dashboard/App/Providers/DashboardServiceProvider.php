<?php

namespace Modules\Dashboard\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Dashboard\App\Repositories\CompanyDashboardRepository;
use Modules\Dashboard\App\Repositories\Contracts\CompanyDashboardRepositoryInterface;
use Modules\Dashboard\App\Repositories\Contracts\DepartmentDashboardRepositoryInterface;
use Modules\Dashboard\App\Repositories\DepartmentDashboardRepository;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CompanyDashboardRepositoryInterface::class,
            CompanyDashboardRepository::class,
        );

        $this->app->bind(
            DepartmentDashboardRepositoryInterface::class,
            DepartmentDashboardRepository::class,
        );
    }

    public function boot(): void
    {
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), giống
     * Report/Evaluation. Không có migration riêng — module này chỉ đọc dữ
     * liệu Project/Task/Identity có sẵn.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Dashboard', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.dashboard.')
                ->group($basePath.'/manager.php');
        }
    }
}
