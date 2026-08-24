<?php

namespace Modules\Identity\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\App\Repositories\Contracts\DepartmentRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\RoleRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Repositories\DepartmentRepository;
use Modules\Identity\App\Repositories\RoleRepository;
use Modules\Identity\App\Repositories\UserRepository;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Bind Repository interface -> implementation (Repository + Service pattern).
     * TẠM THỜI: implementation Eloquent giả lập dữ liệu User/Department —
     * đổi binding sang client gọi API HRM tại đây khi HRM cung cấp API.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Identity', 'Database/migrations'));
        $this->registerRoutes();
    }

    /**
     * Nạp route module (chỉ những file thực sự tồn tại) — module này cần
     * tách biệt khỏi routes/web.php và routes/api.php global vì Identity
     * sở hữu toàn bộ vòng đời auth (Google SSO), theo CLAUDE.md mục 2.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Identity', 'routes');

        if (file_exists($basePath.'/web.php')) {
            Route::middleware('web')->group($basePath.'/web.php');
        }

        if (file_exists($basePath.'/api.php')) {
            Route::middleware('api')->prefix('api')->group($basePath.'/api.php');
        }
    }
}
