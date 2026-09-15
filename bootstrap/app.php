<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // ADICIONE ESTA LINHA EXATAMENTE AQUI
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Isso impede que o Laravel barre o POST da Base44/SellerX
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/nfe/*',
            'api/nfe/emitir'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
