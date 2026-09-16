import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            screens: {
                // Узкие телефоны (iPhone SE, 360px-андроиды) — на них шапка и
                // кнопки уже не помещаются в один ряд, а до sm (640px) ещё
                // далеко: без этой ступени пришлось бы либо ломать вёрстку на
                // 360px, либо резать всё подряд уже с 400px.
                xs: '400px',
            },
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                display: ['"Cormorant Garamond"', 'serif'],
            },
            colors: {
                obsidian: {
                    950: '#060605',
                    900: '#0b0a09',
                    800: '#141210',
                    700: '#1e1b18',
                },
                gold: {
                    200: '#f1e2b8',
                    300: '#e4cd8f',
                    400: '#d4af37',
                    500: '#c9a24b',
                    600: '#a9822f',
                },
                ember: {
                    500: '#c4381f',
                    600: '#9c2a17',
                },
                // Холодный контраст к золоту — без него "aurora" эффект не
                // читается, стекло выглядит просто тёмным, а не преломляющим свет.
                aurora: {
                    400: '#5b6ee8',
                    500: '#3f4fc9',
                    600: '#2c3a9e',
                },
            },
            boxShadow: {
                gold: '0 0 40px -10px rgba(212, 175, 55, 0.35)',
                aurora: '0 0 50px -12px rgba(63, 79, 201, 0.4)',
            },
        },
    },

    plugins: [forms],
};
