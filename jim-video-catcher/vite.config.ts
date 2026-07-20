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
    rollupOptions: {
      // The offscreen document isn't referenced by the manifest, so name it
      // explicitly as an input for crxjs/rollup to build it.
      input: { offscreen: 'src/offscreen.html' },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
});
