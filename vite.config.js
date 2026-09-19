import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Tailwind 4: плагін замінює постс-пайплайн (postcss.config.js
        // видалено) — свій движок (Lightning CSS) сам займається
        // автопрефіксами, окремий autoprefixer більше не потрібен.
        tailwindcss(),
    ],
});
