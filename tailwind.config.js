import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * PathwayTT design tokens.
 *
 * brand  — deep, trustworthy navy used for primary actions, active nav and
 *          links. Reads as "institution / careers service" rather than
 *          "startup", which is the tone job seekers and employers expect.
 * accent — warm gold reserved for small highlights (score badges, focus).
 * Semantic greens / ambers / reds stay Tailwind defaults so status colours
 * mean the same thing everywhere (success, caution, blocked).
 */

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#f2f6fb',
                    100: '#e1eaf5',
                    200: '#c3d4ea',
                    300: '#96b3d8',
                    400: '#618cc0',
                    500: '#3d6ea8',
                    600: '#2c568c',
                    700: '#254672',
                    800: '#213c5f',
                    900: '#1e3350',
                    950: '#132135',
                },
                accent: {
                    50: '#fdf8ec',
                    100: '#faedc8',
                    200: '#f5da8e',
                    300: '#efc253',
                    400: '#e9ac2b',
                    500: '#d8921a',
                    600: '#b96f14',
                    700: '#944f14',
                    800: '#7a3f17',
                    900: '#663417',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(16 32 53 / 0.06), 0 1px 3px 1px rgb(16 32 53 / 0.04)',
            },
        },
    },

    plugins: [forms],
};
