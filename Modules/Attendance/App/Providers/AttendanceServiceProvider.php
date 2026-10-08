<?php

namespace Modules\Attendance\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AttendanceServiceProvider extends ServiceProvider
{
    /**
     * Module hiện chỉ có frontend (UI chấm công/nghỉ phép dùng dữ liệu mock,
     * xem resources/js/pages/Employee*.vue) — chưa có Repository/Service/Model
     * thật nên chưa cần bind gì ở đây. Bind interface -> implementation khi
     * module này có backend thật (bảng chấm công/nghỉ phép).
     */
    public function register(): void
    {
    }

    public function boot(): void
    {
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
