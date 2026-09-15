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

        if ($this->app->runningInConsole()) {
            // bot-точки входа подключаются отдельной artisan-командой при
            // необходимости, а не на каждый CLI-вызов (миграции, tinker и т.п.)
            return;
        }

        $autoloader->loadEntrypoint('web');
    }
}
