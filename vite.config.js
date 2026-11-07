import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// pdf.js (Titelbilder der Georgsboten) lädt Schriften, CMaps und WASM-Decoder
// bei Bedarf aus einem Verzeichnis nach. Diese Dateien deshalb unter festem
// Namen in je ein Verzeichnis je pdf.js-Version legen und nie als data:-URL
// einbetten (CSP connect-src 'self').
const pdfjsVersion = JSON.parse(readFileSync(new URL('./node_modules/pdfjs-dist/package.json', import.meta.url))).version;
const PDFJS_DATEN = /node_modules\/pdfjs-dist\/(standard_fonts|cmaps|wasm|iccs)\//;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/map.js', 'resources/js/titelbild.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // Web-Worker (MapLibre) als ES-Modul bündeln.
    worker: {
        format: 'es',
    },
    build: {
        assetsInlineLimit: (datei) => (PDFJS_DATEN.test(datei.replaceAll('\\', '/')) ? false : undefined),
        rollupOptions: {
            output: {
                assetFileNames: (asset) => {
                    const herkunft = (asset.originalFileNames?.[0] ?? '').replaceAll('\\', '/');
                    const treffer = herkunft.match(PDFJS_DATEN);

                    return treffer ? `pdfjs-${pdfjsVersion}/${treffer[1]}/[name][extname]` : 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
