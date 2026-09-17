import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { reveal } from './directives/reveal';
import { glow } from './directives/glow';
import { parallax } from './directives/parallax';
import { magnetic } from './directives/magnetic';
import CommandPalette from './Components/CommandPalette.vue';
import PageTransition from './Components/PageTransition.vue';
import { startSmoothScroll, stopSmoothScroll, handleAnchorClick, resetScroll } from './lib/smoothScroll';
import { startCursor, stopCursor } from './lib/cursor';
import { startAuroraShader, stopAuroraShader } from './lib/auroraShader';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * «Тяжёлые» украшения — инерционный скролл, кастомный курсор и шейдерный
 * фон — и каждое решает само, уместно ли оно здесь: см. lib/motion.js. На
 * телефоне остаётся то же, что и было, ПК получает полный набор.
 *
 * Кроме /admin: там это рабочий инструмент с таблицами и формами, а не
 * маркетинговая страница — WebGL-шейдер под ней только грузил GPU (видно
 * было по "GPU stall due to ReadPixels" в консоли) без единой причины,
 * а кастомный курсор и перехват скролла Lenis мешали бы работе со
 * списками. Стеклянная стилизация CSS в админке при этом остаётся как
 * была — тут выключается только JS-слой.
 *
 * Проверяется не только один раз при загрузке, а на каждый переход: это
 * SPA, и человек может уйти на /admin (или обратно) без перезагрузки
 * страницы — a значит, и включаться/выключаться decorations должны вместе
 * с ним, а не только при первом заходе.
 */
function syncDecorations() {
    if (window.location.pathname.startsWith('/admin')) {
        stopSmoothScroll();
        stopCursor();
        stopAuroraShader();
        return;
    }

    startSmoothScroll();
    startCursor();
    startAuroraShader();
}

function startDecorations() {
    syncDecorations();

    // Якорные ссылки ведёт Lenis, иначе «Посади» в шапке прыгает мимо
    // всей плавности, ради которой он и ставился.
    document.addEventListener('click', handleAnchorClick);

    // Inertia меняет страницу без перезагрузки: браузер сам скролл
    // наверх не вернёт, и новая страница открывается с середины. Заодно
    // здесь же решаем, нужны ли decorations на странице, куда перешли.
    document.addEventListener('inertia:navigate', () => {
        syncDecorations();
        resetScroll();
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h('div', [h(PageTransition, () => h(App, props)), h(CommandPalette)]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .directive('reveal', reveal)
            .directive('glow', glow)
            .directive('parallax', parallax)
            .directive('magnetic', magnetic)
            .mount(el);

        startDecorations();

        return app;
    },
    progress: {
        // Читаем из палитры, а не хексом: акцент задаётся в админке, и
        // полоска загрузки не должна оставаться золотой, когда весь сайт
        // уже другого цвета.
        color: getComputedStyle(document.documentElement)
            .getPropertyValue('--gold-400')
            .trim()
            .replace(/^(\d+)\s+(\d+)\s+(\d+)$/, 'rgb($1,$2,$3)') || '#d4af37',
    },
});
