<?php

namespace Modules\DonTu\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DonTuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('DonTu', 'Database/migrations'));

        $seeder = module_path('DonTu', 'Database/seeders/DonTuStatusSetSeeder.php');
        if (file_exists($seeder) && $this->app->runningInConsole()) {
            // Seeder gọi thủ công: php artisan db:seed --class=Modules\\DonTu\\Database\\Seeders\\DonTuStatusSetSeeder
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $basePath = module_path('DonTu', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.')
                ->group($basePath.'/manager.php');
        }
    }
}
