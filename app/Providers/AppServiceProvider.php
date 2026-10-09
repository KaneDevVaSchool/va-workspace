<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // CSS PWA: trình duyệt chỉ tải khi display-mode standalone (Drupal PWA pattern).
        Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
            if ($src === 'resources/css/pwa-standalone.css') {
                return [
                    'media' => '(display-mode: standalone), (display-mode: fullscreen)',
                ];
            }

            return [];
        });
    }
}
