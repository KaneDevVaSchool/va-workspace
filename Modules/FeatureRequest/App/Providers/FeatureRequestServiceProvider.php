<?php

namespace Modules\FeatureRequest\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\FeatureRequest\App\Repositories\Contracts\FeatureRequestRepositoryInterface;
use Modules\FeatureRequest\App\Repositories\FeatureRequestRepository;

class FeatureRequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FeatureRequestRepositoryInterface::class, FeatureRequestRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('FeatureRequest', 'Database/migrations'));
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), giống Evaluation.
     * routes/manager.php dùng cho mọi user đã đăng nhập (ghi nhận/sửa/xoá của
     * bản thân); routes/superadmin.php dành riêng cho xử lý/duyệt. Tên file
     * chỉ phân loại khu vực nghiệp vụ chứ không quyết định URL.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('FeatureRequest', 'routes');

        if (file_exists($basePath . '/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.')
                ->group($basePath . '/manager.php');
        }

        if (file_exists($basePath . '/superadmin.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.')
                ->group($basePath . '/superadmin.php');
        }
    }
}
