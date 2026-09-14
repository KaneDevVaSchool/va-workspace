<?php

namespace Modules\Credential\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Credential\App\Repositories\Contracts\CredentialProviderRepositoryInterface;
use Modules\Credential\App\Repositories\Contracts\CredentialRepositoryInterface;
use Modules\Credential\App\Repositories\CredentialProviderRepository;
use Modules\Credential\App\Repositories\CredentialRepository;

class CredentialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CredentialRepositoryInterface::class, CredentialRepository::class);
        $this->app->bind(CredentialProviderRepositoryInterface::class, CredentialProviderRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Credential', 'Database/migrations'));
        $this->loadViewsFrom(module_path('Credential', 'resources/views'), 'credential');
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session) — giống hệt
     * ProjectServiceProvider. Trang Vue /manager/credential (SPA, phục vụ
     * qua fallback trong routes/web.php gốc) ≠ path JSON /api/credential/*.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Credential', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.credential.')
                ->group($basePath.'/manager.php');
        }
    }
}
