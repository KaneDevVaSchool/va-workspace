<?php

namespace Modules\Evaluation\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;
use Modules\Evaluation\App\Repositories\EvaluationCriteriaRepository;
use Modules\Evaluation\App\Repositories\EvaluationCriterionTypeRepository;

class EvaluationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            EvaluationCriteriaRepositoryInterface::class,
            EvaluationCriteriaRepository::class,
        );
        $this->app->bind(
            EvaluationCriterionTypeRepositoryInterface::class,
            EvaluationCriterionTypeRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Evaluation', 'Database/migrations'));
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), giống WorkspaceConfig.
     * Trang Vue /manager/workspace-config/evaluation ≠ path JSON /api/evaluation/*.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Evaluation', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.evaluation.')
                ->group($basePath.'/manager.php');
        }
    }
}
