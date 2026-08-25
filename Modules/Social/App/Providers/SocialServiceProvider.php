<?php

namespace Modules\Social\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Social\App\Repositories\Contracts\SocialCommentRepositoryInterface;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Repositories\SocialCommentRepository;
use Modules\Social\App\Repositories\SocialPostRepository;

class SocialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SocialPostRepositoryInterface::class,
            SocialPostRepository::class,
        );
        $this->app->bind(
            SocialCommentRepositoryInterface::class,
            SocialCommentRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Social', 'Database/migrations'));
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), giống Evaluation.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Social', 'routes');

        if (file_exists($basePath.'/api.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->group($basePath.'/api.php');
        }
    }
}
