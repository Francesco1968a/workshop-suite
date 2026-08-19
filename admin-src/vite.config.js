import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// One entry per WP-admin panel bundle. Each panel is still loaded by
// WordPress as its own independent <script type="module">, exactly like
// today's compiled bundles — so each entry is built self-contained
// (its own copy of Vue/Element Plus, no shared chunks) rather than as a
// single multi-entry library, to avoid changing anything on the PHP side.
//
// Add a bundle here once its .vue source exists; until then the old
// compiled file in assets/dist/ stays untouched and keeps working.
const ENTRIES = {
  calendario: fileURLToPath(new URL('./src/calendario/main.js', import.meta.url)),
  archivio: fileURLToPath(new URL('./src/archivio/main.js', import.meta.url)),
  messaggi: fileURLToPath(new URL('./src/messaggi/main.js', import.meta.url)),
  riepilogo: fileURLToPath(new URL('./src/riepilogo/main.js', import.meta.url)),
  partecipante: fileURLToPath(new URL('./src/partecipante/main.js', import.meta.url)),
  'partecipanti-lista': fileURLToPath(new URL('./src/partecipanti-lista/main.js', import.meta.url)),
  ringraziamento: fileURLToPath(new URL('./src/ringraziamento/main.js', import.meta.url)),
  admin: fileURLToPath(new URL('./src/admin/main.js', import.meta.url)),
  'admin-theme': fileURLToPath(new URL('./src/admin-theme/main.js', import.meta.url)),
};

const target = process.env.WS_BUNDLE || 'calendario';
if (!ENTRIES[target]) {
  throw new Error(`Unknown bundle "${target}". Known: ${Object.keys(ENTRIES).join(', ')}`);
}

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: fileURLToPath(new URL('../assets/dist', import.meta.url)),
    emptyOutDir: false, // other bundles' compiled output lives in the same dir, don't wipe it
    cssCodeSplit: false,
    rollupOptions: {
      input: ENTRIES[target],
      output: {
        format: 'es',
        inlineDynamicImports: true,
        entryFileNames: `${target}.js`,
        assetFileNames: (asset) => (asset.name?.endsWith('.css') ? `${target}.css` : 'assets/[name][extname]'),
      },
    },
  },
});
