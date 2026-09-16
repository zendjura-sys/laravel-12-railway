<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover — иначе на телефонах с вырезом (iPhone X и
             новее) контент не доходит до краёв экрана и по бокам остаются
             чёрные поля вместо полноэкранной сцены. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Красит адресную строку браузера на телефоне в цвет фона сайта:
             без этого поверх тёмной страницы висит светлая полоса. --}}
        <meta name="theme-color" content="#060605">
        <meta name="description" content="Monsory Family — закрита родина на RP-сервері: спільний особняк і автопарк, свій звʼязок, спільні операції та власний кодекс.">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        {{-- figtree убран: в теме он нигде не используется (sans — Manrope,
             display — Cormorant), но три его начертания всё равно грузились
             на каждой странице. --}}
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|manrope:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
