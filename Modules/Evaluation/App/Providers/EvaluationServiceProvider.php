<?php

namespace Modules\Evaluation\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationPositionRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationScoreKitRepositoryInterface;
use Modules\Evaluation\App\Repositories\EvaluationCriteriaRepository;
use Modules\Evaluation\App\Repositories\EvaluationCriterionTypeRepository;
use Modules\Evaluation\App\Repositories\EvaluationPositionRepository;
use Modules\Evaluation\App\Repositories\EvaluationScoreKitRepository;

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
        $this->app->bind(
            EvaluationPositionRepositoryInterface::class,
            EvaluationPositionRepository::class,
        );
        $this->app->bind(
            EvaluationScoreKitRepositoryInterface::class,
            EvaluationScoreKitRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Evaluation', 'Database/migrations'));
        $this->loadViewsFrom(module_path('Evaluation', 'resources/views'), 'evaluation');
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
