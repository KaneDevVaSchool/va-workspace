<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GeneratePwaIosSplashCommand extends Command
{
    protected $signature = 'pwa:ios-splash';

    protected $description = 'Sinh PNG apple-touch-startup-image cho iOS (config/pwa_ios.php)';

    public function handle(): int
    {
        $script = base_path('scripts/generate-pwa-ios-splash.py');
        if (! is_file($script)) {
            $this->error('Thiếu scripts/generate-pwa-ios-splash.py');

            return self::FAILURE;
        }

        $process = new Process(['python', $script], base_path());
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Sinh splash thất bại — cần Python 3 + Pillow (pip install Pillow).');

            return self::FAILURE;
        }

        $this->info('Xong. Bump PWA_SPLASH_VERSION nếu Safari vẫn cache splash cũ.');

        return self::SUCCESS;
    }
}
