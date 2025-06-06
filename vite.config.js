import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import { dirname, resolve } from 'node:path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/client.js',
                'resources/js/admin.js'
            ],
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
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '@client': resolve(__dirname, 'resources/js/Client'),
            '@admin': resolve(__dirname, 'resources/js/Admin'),
            '@/Components': resolve(__dirname, 'resources/js/Client/Components'),
            '@/Layouts': resolve(__dirname, 'resources/js/Client/Layouts'),
            '@/Pages': resolve(__dirname, 'resources/js/Client/Pages'),
            'Components': resolve(__dirname, 'resources/js/Client/Components'),
            'Layouts': resolve(__dirname, 'resources/js/Client/Layouts'),
            'Pages': resolve(__dirname, 'resources/js/Client/Pages'),
            '@admin/Components': resolve(__dirname, 'resources/js/Admin/Components'),
            '@admin/Layouts': resolve(__dirname, 'resources/js/Admin/Layouts'),
            '@admin/Pages': resolve(__dirname, 'resources/js/Admin/Pages')
        }
    },
    define: {
        'process.env': {
            VITE_REVERB_APP_KEY: process.env.VITE_REVERB_APP_KEY,
            VITE_REVERB_HOST: process.env.VITE_REVERB_HOST,
            VITE_REVERB_PORT: process.env.VITE_REVERB_PORT,
            VITE_REVERB_SCHEME: process.env.VITE_REVERB_SCHEME,
        }
    }
});
