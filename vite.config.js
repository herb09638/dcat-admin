import { defineConfig } from 'vite';
import { resolve } from 'path';
import { glob } from 'glob';
import commonjs from '@rollup/plugin-commonjs';
import nodeResolve from '@rollup/plugin-node-resolve';

const isProduction = process.env.NODE_ENV === 'production';
const distPath = isProduction ? 'resources/dist' : 'resources/pre-dist';

// Find all extra JS files
const extraJsFiles = glob.sync('resources/assets/dcat/extra/*.js');

// Build input entries
const input = {
    // AdminLTE
    'adminlte/adminlte': resolve(__dirname, 'resources/assets/adminlte/js/AdminLTE.js'),

    // Dcat App
    'dcat/js/dcat-app': resolve(__dirname, 'resources/assets/dcat/js/dcat-app.js'),
};

// Add extra JS files dynamically
extraJsFiles.forEach(file => {
    const name = file.replace('resources/assets/', '').replace('.js', '');
    input[name] = resolve(__dirname, file);
});

export default defineConfig({
    build: {
        outDir: distPath,
        emptyOutDir: false,
        manifest: false,
        minify: isProduction,
        sourcemap: true,
        rollupOptions: {
            input,
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
            // Treat these as external (loaded separately)
            external: [
                'jquery',
                'lodash',
            ],
            plugins: [
                nodeResolve(),
                commonjs({
                    // Handle UMD modules like sweetalert2
                    transformMixedEsModules: true,
                }),
            ],
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/assets'),
        },
    },
});
