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
import { startSmoothScroll, handleAnchorClick, resetScroll } from './lib/smoothScroll';
import { startCursor } from './lib/cursor';
import { startAuroraShader } from './lib/auroraShader';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * «Тяжёлые» украшения — инерционный скролл, кастомный курсор и шейдерный
 * фон — включаются ПОСЛЕ монтирования и каждое решает само, уместно ли
 * оно здесь: см. lib/motion.js. На телефоне остаётся то же, что и было,
 * ПК получает полный набор.
 */
function startDecorations() {
    startSmoothScroll();
    startCursor();
    startAuroraShader();

    // Якорные ссылки ведёт Lenis, иначе «Посади» в шапке прыгает мимо
    // всей плавности, ради которой он и ставился.
    document.addEventListener('click', handleAnchorClick);

    // Inertia меняет страницу без перезагрузки: браузер сам скролл
    // наверх не вернёт, и новая страница открывается с середины.
    document.addEventListener('inertia:navigate', resetScroll);
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
