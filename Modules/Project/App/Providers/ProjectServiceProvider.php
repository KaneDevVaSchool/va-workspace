<?php

namespace Modules\Project\App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Project\App\Console\Commands\AutoStartProjectsCommand;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskWorklogRepositoryInterface;
use Modules\Project\App\Repositories\ProjectRepository;
use Modules\Project\App\Repositories\TaskAttachmentRepository;
use Modules\Project\App\Repositories\TaskRepository;
use Modules\Project\App\Repositories\TaskWorklogRepository;

class ProjectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProjectRepositoryInterface::class,
            ProjectRepository::class,
        );

        $this->app->bind(
            TaskRepositoryInterface::class,
            TaskRepository::class,
        );

        $this->app->bind(
            TaskAttachmentRepositoryInterface::class,
            TaskAttachmentRepository::class,
        );

        $this->app->bind(
            TaskWorklogRepositoryInterface::class,
            TaskWorklogRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Project', 'Database/migrations'));
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                AutoStartProjectsCommand::class,
            ]);
        }

        // Lịch tự động chuyển trạng thái dự án khi đến ngày bắt đầu — mục D2.
        // Không có tiền lệ Schedule:: nào khác trong repo, đăng ký qua
        // $this->app->booted() để container đã sẵn sàng resolve Schedule.
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('project:auto-start')->dailyAt('00:05');
        });
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session) — giống hệt
     * EvaluationServiceProvider. Trang Vue /manager/project (SPA, phục vụ
     * qua fallback trong routes/web.php gốc) ≠ path JSON /api/project/*.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Project', 'routes');

        if (file_exists($basePath.'/manager.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->name('api.project.')
                ->group($basePath.'/manager.php');
        }
    }
}
