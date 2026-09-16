<!DOCTYPE html>
@php
    // Оформление читается из настроек (админка → Дизайн), а не собирается
    // в бандл: смена акцента или фавикона не должна требовать пересборки
    // фронтенда и деплоя.
    $design = App\Support\DesignSettings::all();
    $accentCss = App\Support\DesignSettings::accentCss();
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-effects="{{ $design['effects'] }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover — иначе на телефонах с вырезом (iPhone X и
             новее) контент не доходит до краёв экрана и по бокам остаются
             чёрные поля вместо полноэкранной сцены. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Красит адресную строку браузера на телефоне в цвет фона сайта:
             без этого поверх тёмной страницы висит светлая полоса. --}}
        <meta name="theme-color" content="#060605">

        @if ($design['faviconUrl'])
            <link rel="icon" href="{{ $design['faviconUrl'] }}">
        @endif

        <meta name="description" content="Monsory Family — закрита родина на RP-сервері: спільний особняк і автопарк, свій звʼязок, спільні операції та власний кодекс.">

        <title inertia>{{ $design['siteName'] ?: config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        {{-- figtree убран: в теме он нигде не используется (sans — Manrope,
             display — Cormorant), но три его начертания всё равно грузились
             на каждой странице. --}}
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|manrope:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])

        @if ($accentCss)
            {{-- Пять переменных перекрашивают весь сайт: палитра золота в
                 Tailwind объявлена как rgb(var(--gold-N)).

                 Обязательно ПОСЛЕ @vite: дефолты живут в :root внутри
                 app.css, селектор тот же, и при равной специфичности
                 побеждает тот, что идёт в документе последним. Стоя выше,
                 переопределение молча не работало. --}}
            <style>:root{ {!! $accentCss !!} }</style>
        @endif
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
