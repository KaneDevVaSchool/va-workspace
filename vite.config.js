import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { copyFileSync, mkdirSync } from 'fs';
import { resolve } from 'path';

function copyPdfWorker() {
    const destDir = resolve(__dirname, 'public/vendor/pdfjs');
    mkdirSync(destDir, { recursive: true });
    copyFileSync(
        resolve(__dirname, 'node_modules/pdfjs-dist/build/pdf.worker.min.mjs'),
        resolve(destDir, 'pdf.worker.min.mjs'),
    );
}

export default defineConfig({
    plugins: [
        {
            name: 'copy-pdf-worker',
            buildStart() {
                copyPdfWorker();
            },
        },
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
            // Trỏ thẳng vào Modules/{Ten}/resources/js (nơi module Vue code
            // thực sự nằm — xem Modules/Example, Modules/Identity), không
            // phải resources/js/Modules (thư mục không tồn tại).
            '@modules': resolve(__dirname, 'Modules'),
            '@theme': resolve(__dirname, 'resources/css'),
        },
    },
});
