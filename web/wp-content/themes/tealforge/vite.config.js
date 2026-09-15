import { defineConfig } from 'vite';

export default defineConfig({
  base: './',
  publicDir: false,
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: [
        'assets/scripts/main.js',
        ...['woocommerce', 'product-detail', 'cart', 'checkout', 'account', 'contact', 'error-page', 'animations']
          .map((name) => `assets/styles/sections/${name}.css`),
      ],
      output: {
        entryFileNames: 'assets/scripts/[name].[hash].js',
        chunkFileNames: 'assets/scripts/[name].[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name ?? '';

          if (name.endsWith('.css')) {
            return 'assets/styles/[name].[hash][extname]';
          }

          return 'assets/[name].[hash][extname]';
        },
      },
    },
  },
});
