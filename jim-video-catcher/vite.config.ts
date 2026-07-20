import { defineConfig } from 'vite';
import { crx } from '@crxjs/vite-plugin';
import manifest from './manifest.config';

// Outputs a loadable, unpacked extension into /dist.
// `npm run dev` rebuilds on save; reload the extension in edge://extensions after each build.
export default defineConfig({
  plugins: [crx({ manifest })],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    sourcemap: true,
  },
  server: {
    port: 5173,
    strictPort: true,
  },
});
