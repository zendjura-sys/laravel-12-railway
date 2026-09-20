<?php

namespace App\Providers;

use App\Addons\AddonAutoloader;
use Illuminate\Support\ServiceProvider;

/**
 * Подключает активные Core/Modules/Plugins к рантайму приложения:
 * их PSR-4 автозагрузку и web/admin/api-точки входа.
 *
 * Регистрируется в bootstrap/providers.php.
 */
class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AddonAutoloader::class);
    }

    public function boot(): void
    {
        $autoloader = $this->app->make(AddonAutoloader::class);
        $autoloader->bootActive();

        // events — регистрация Event::listen() для межмодульной интеграции
        // (например, Progression слушает report.created от Reports). Нужна
        // и в HTTP, и в консоли (queue worker, artisan-команды), поэтому
        // грузится всегда, до проверки runningInConsole().
        $autoloader->loadEntrypoint('events');

        if ($this->app->runningInConsole()) {
            // bot-точки входа подключаются отдельной artisan-командой при
            // необходимости, а не на каждый CLI-вызов (миграции, tinker и т.п.)
            return;
        }

        $autoloader->loadEntrypoint('web');
        $autoloader->loadEntrypoint('admin');
        // api — токен-ендпоінти для мобільного застосунку (Sanctum), той
        // самий require-механізм, що й web/admin: модуль сам оголошує свої
        // Route::middleware(['auth:sanctum'])->group(...).
        $autoloader->loadEntrypoint('api');
    }
}
