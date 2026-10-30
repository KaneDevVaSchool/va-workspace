<?php

namespace Modules\Attendance\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AttendanceServiceProvider extends ServiceProvider
{
    /**
     * Route API nghỉ phép, duyệt bảng công và giờ chấm (gửi về HRM).
     * Giờ vào/ra không lưu bảng riêng trên Workspace.
     */
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(module_path('Attendance', 'resources/views'), 'attendance');
        $this->registerRoutes();
    }

    /**
     * Nạp 4 loại route của module (chỉ những file thực sự tồn tại).
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Attendance', 'routes');

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
