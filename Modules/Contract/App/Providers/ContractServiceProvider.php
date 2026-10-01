<?php

namespace Modules\Contract\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ContractServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Contract', 'Database/migrations'));
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $basePath = module_path('Contract', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.contract.')
                ->group($basePath.'/manager.php');
        }
    }
}
