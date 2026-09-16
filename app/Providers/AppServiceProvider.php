<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
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

        // Без App\Providers\EventServiceProvider (его нет в Laravel 12 по
        // умолчанию) фреймворк сам по себе НЕ подписывает этот листенер —
        // без этой строки письмо с подтверждением email при регистрации
        // никогда не отправлялось, даже с рабочим SMTP.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Force HTTPS when APP_URL uses https
        if (str_starts_with(env('APP_URL', ''), 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(env('APP_URL'));
        }
    }
}
