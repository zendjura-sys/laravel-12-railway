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
        Vite::prefetch(concurrency: 3);

        // Force HTTPS when APP_URL uses https
        if (str_starts_with(env('APP_URL', ''), 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(env('APP_URL'));
        }
    }
}
