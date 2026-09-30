<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Lệnh Identity — đăng ký qua property, không gọi $this->commands() (đệ quy).
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \Modules\Identity\App\Console\EnsureSuperAdminCommand::class,
        \Modules\Identity\App\Console\GenerateVapidKeysCommand::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // Làm nóng cache JWKS VA-HRM (SSO) — giảm khả năng cache-miss đúng
        // lúc user đang login, xem Modules/Identity/App/Console/WarmHrmJwksCommand.
        $schedule->command('identity:hrm-warm-jwks')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
