<?php

namespace Modules\Chat\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Chat\App\Repositories\ConversationRepository;
use Modules\Chat\App\Repositories\Contracts\ConversationRepositoryInterface;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;
use Modules\Chat\App\Repositories\MessageRepository;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Chat', 'Database/migrations'));
        $this->registerRoutes();
    }

    /**
     * JSON SPA dưới prefix /api (middleware web + session), giống Social/WorkspaceConfig.
     * Phase 1 chưa có khu quản trị nên chỉ đăng ký routes/api.php.
     */
    protected function registerRoutes(): void
    {
        $basePath = module_path('Chat', 'routes');

        if (file_exists($basePath.'/api.php')) {
            Route::middleware('web')
                ->prefix('api')
                ->group($basePath.'/api.php');
        }
    }
}
