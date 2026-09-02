import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/dashboard.jsx', 'resources/js/history.js', 'resources/js/charging-curve.js', 'resources/js/telemetry.js', 'resources/js/trips.js', 'resources/js/planner.js', 'resources/js/favorites.js', 'resources/js/server-info.js', 'resources/js/obd-stats.js'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
