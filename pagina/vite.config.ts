/**
 * Página web pública (ADR-021, ADR-023). Se sirve bajo `/pagina/` desde el
 * backend (`App\Controller\PaginaController`), así que la compilación va a
 * `backend/public/pagina` y la API es del mismo origen (`/api/publico`).
 *
 * Reutiliza del frontend, por alias, el mapa del bus (`shared/bus-map`,
 * `core/croquis`: el mismo croquis que ve la taquilla) y los inputs FormKit
 * sobre PrimeVue (`shared/formkit`).
 *
 * `npm run build` compila el cliente, luego el render de servidor
 * (`src/entry-server.ts`, en `.ssr/`) y `scripts/prerender.mjs` escribe el
 * HTML de las páginas públicas en cada idioma (SEO).
 */
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import Components from 'unplugin-vue-components/vite'
import { PrimeVueResolver } from '@primevue/auto-import-resolver'
import Icons from 'unplugin-icons/vite'
import AutoImport from 'unplugin-auto-import/vite'

const aqui = (ruta: string) => fileURLToPath(new URL(ruta, import.meta.url))
const frontend = aqui('../frontend/src')
/** Backend para `npm run dev` (proxy de `/api` y de Mercure). */
const backend = process.env.PAGINA_BACKEND ?? 'http://localhost'

/**
 * Los archivos del frontend que se reutilizan importan estos paquetes; sin
 * `dedupe` se resolverían desde `frontend/node_modules`: una segunda copia de
 * PrimeVue sin el tema registrado (selects transparentes) y de Vue/FormKit.
 */
const UNA_SOLA_COPIA = [
  'vue',
  'primevue',
  '@primevue/core',
  '@primevue/icons',
  '@primeuix/styled',
  '@primeuix/styles',
  '@primeuix/utils',
  '@primeuix/themes',
  '@formkit/vue',
  '@formkit/core',
  '@formkit/inputs',
  '@formkit/i18n',
  'pinia',
]

export default defineConfig({
  base: '/pagina/',
  plugins: [
    vue(),
    tailwindcss(),
    // Los archivos del frontend que se reutilizan usan las APIs de Vue sin importarlas.
    AutoImport({ imports: ['vue'], dts: 'src/auto-imports.d.ts' }),
    Components({ dts: false, resolvers: [PrimeVueResolver()] }),
    Icons({ compiler: 'vue3' }),
  ],
  resolve: {
    alias: [
      { find: /^@\/core\/croquis\/(.*)$/, replacement: `${frontend}/core/croquis/$1` },
      { find: /^@\/shared\/bus-map\/(.*)$/, replacement: `${frontend}/shared/bus-map/$1` },
      { find: /^@\/shared\/formkit\/(.*)$/, replacement: `${frontend}/shared/formkit/$1` },
      { find: /^@\//, replacement: `${aqui('./src')}/` },
    ],
    dedupe: UNA_SOLA_COPIA,
  },
  optimizeDeps: {
    // Todo PrimeVue en una sola pasada del optimizador (una sola copia del tema).
    include: [
      'primevue/config',
      'primevue/button',
      'primevue/select',
      'primevue/datepicker',
      'primevue/checkbox',
      'primevue/toggleswitch',
      'primevue/inputtext',
      'primevue/inputmask',
      'primevue/textarea',
      'primevue/message',
      'primevue/dialog',
      'primevue/drawer',
      'primevue/skeleton',
      'primevue/tag',
      'primevue/progressspinner',
      'primevue/iconfield',
      'primevue/inputicon',
      '@primeuix/themes',
      '@primeuix/themes/aura',
      '@formkit/vue',
      '@formkit/i18n',
      'vue-i18n',
    ],
  },
  ssr: {
    // Se compilan dentro del bundle de servidor (no se cargan de node_modules en crudo).
    noExternal: ['primevue', '@primevue/core', '@primevue/icons', '@primeuix/styled', '@primeuix/utils', '@primeuix/themes', '@formkit/vue', '@formkit/core', '@formkit/inputs', '@formkit/i18n'],
  },
  define: {
    __VUE_I18N_FULL_INSTALL__: true,
    __VUE_I18N_LEGACY_API__: false,
    __INTLIFY_PROD_DEVTOOLS__: false,
  },
  build: {
    outDir: aqui('../backend/public/pagina'),
    emptyOutDir: true,
  },
  server: {
    port: 9100,
    host: '0.0.0.0',
    // Sin `changeOrigin`: el backend ve el host de Vite y arma la vuelta del
    // banco (3-D Secure) en este mismo origen, como en producción.
    proxy: {
      '/api': { target: backend, changeOrigin: false, secure: false },
      '/.well-known/mercure': { target: backend, changeOrigin: false, secure: false },
    },
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/__tests__/*.spec.ts'],
  },
})
