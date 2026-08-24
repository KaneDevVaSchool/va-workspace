<?php

namespace Modules\Example\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Example\App\Repositories\Contracts\ExampleRepositoryInterface;
use Modules\Example\App\Repositories\ExampleRepository;

class ExampleServiceProvider extends ServiceProvider
{
    /**
     * Bind Repository interface -> implementation (Repository + Service pattern).
     * Mỗi module copy provider này và đổi tên, KHÔNG bind trực tiếp Eloquent
     * model trong Controller/Service.
     */
    public function register(): void
    {
        $this->app->bind(ExampleRepositoryInterface::class, ExampleRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Example', 'Database/migrations'));
        $this->loadViewsFrom(module_path('Example', 'resources/views'), 'example');
        $this->registerRoutes();
    }

    /**
     * Nạp 4 loại route của module (chỉ những file thực sự tồn tại).
     * Mặc định dự án ưu tiên đăng ký route ở cấp global (routes/manager.php,
     * routes/superadmin.php, routes/web.php, routes/api.php) — chỉ bật route
     * riêng ở đây khi module thật sự cần tách biệt.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Example', 'routes');

        if (file_exists($basePath . '/web.php')) {
            Route::middleware('web')->group($basePath . '/web.php');
        }

        if (file_exists($basePath . '/api.php')) {
            Route::middleware('api')->prefix('api')->group($basePath . '/api.php');
        }

        if (file_exists($basePath . '/manager.php')) {
            Route::middleware('web')->prefix('manager')->name('manager.')->group($basePath . '/manager.php');
        }

        if (file_exists($basePath . '/superadmin.php')) {
            Route::middleware('web')->prefix('superadmin')->name('superadmin.')->group($basePath . '/superadmin.php');
        }
    }
}
