/**
 * Página web pública (ADR-021). Se sirve bajo `/pagina/` desde el backend
 * (`App\Controller\PaginaController`), así que la compilación va a
 * `backend/public/pagina` y la API es del mismo origen (`/api/publico`).
 *
 * Reutiliza del frontend, por alias, el mapa del bus (`shared/bus-map`,
 * `core/croquis`: el mismo croquis que ve la taquilla) y los inputs FormKit
 * sobre PrimeVue (`shared/formkit`).
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
/** Backend para `npm run dev` (proxy de `/api`). */
const backend = process.env.PAGINA_BACKEND ?? 'https://localhost'

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
    // Los archivos del frontend no deben traer su propia copia de Vue.
    dedupe: ['vue'],
  },
  build: {
    outDir: aqui('../backend/public/pagina'),
    emptyOutDir: true,
  },
  server: {
    port: 9100,
    host: '0.0.0.0',
    proxy: { '/api': { target: backend, changeOrigin: true, secure: false } },
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/__tests__/*.spec.ts'],
  },
})
