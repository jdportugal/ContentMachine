import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/imoplanta.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        chunkSizeWarningLimit: 700,   // imoplanta.js carries three.js (~620 kB), loaded only on Imoplanta's property page
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
