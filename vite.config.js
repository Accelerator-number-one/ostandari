import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import {fileURLToPath, URL} from 'node:url';

export default defineConfig({
  plugins: [laravel({input:['resources/css/app.css','resources/js/app.ts'],refresh:true}),tailwindcss()],
  resolve: {alias: {'@':fileURLToPath(new URL('./resources/js/portal',import.meta.url))}},
  // Cached pages can still request a previous deployment's immutable asset URLs.
  build: {manifest:'manifest.json', sourcemap:false, emptyOutDir:false},
});
