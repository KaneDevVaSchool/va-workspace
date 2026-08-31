<?php

namespace Modules\Report\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Report\App\Repositories\Contracts\ReportRepositoryInterface;
use Modules\Report\App\Repositories\ReportRepository;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ReportRepositoryInterface::class,
            ReportRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Report', 'Database/migrations'));
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), giống Evaluation.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Report', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.report.')
                ->group($basePath.'/manager.php');
        }
    }
}
