import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/landing.css', 'resources/js/app.js', 'resources/js/welcome.js', 'resources/js/landing-nav.js', 'resources/js/chat-widget.js'],
            refresh: true,
        }),
    ],
});
