import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { reveal } from './directives/reveal';
import { glow } from './directives/glow';
import CommandPalette from './Components/CommandPalette.vue';
import PageTransition from './Components/PageTransition.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({
            render: () => h('div', [h(PageTransition, () => h(App, props)), h(CommandPalette)]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .directive('reveal', reveal)
            .directive('glow', glow)
            .mount(el);
    },
    progress: {
        color: '#d4af37',
    },
});
