import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const { LARAVEL_DEV_SERVER_URL } = loadEnv(mode, process.cwd(), 'LARAVEL_');

    return {
        server: {
            host: '127.0.0.1',
            port: 5173,
            strictPort: true,
            proxy: {
                '^/(?!@|__vite|resources/|node_modules/)': {
                    target: LARAVEL_DEV_SERVER_URL || 'http://127.0.0.1:8000',
                    changeOrigin: false,
                },
            },
            watch: {
                ignored: ['**/storage/**', '**/vendor/**'],
            },
        },
        plugins: [
            laravel({
                input: ['resources/client_area/default/assets/css/app.css','resources/client_area/default/assets/js/app.js'],
                refresh: true,
            }),
        ],
    };
});
