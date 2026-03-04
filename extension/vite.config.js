import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        popup: resolve(__dirname, 'popup/main.js'),
        'service-worker': resolve(__dirname, 'background/service-worker.js'),
        'content-generic': resolve(__dirname, 'content-scripts/generic.js'),
        'content-linkedin': resolve(__dirname, 'content-scripts/linkedin.js'),
        'content-twitter': resolve(__dirname, 'content-scripts/twitter.js'),
        'content-gmail': resolve(__dirname, 'content-scripts/gmail.js'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name].[ext]',
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, '.'),
    },
  },
});
