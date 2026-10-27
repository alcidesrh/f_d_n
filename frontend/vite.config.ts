import { fileURLToPath, URL } from "node:url";

import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import vueDevTools from "vite-plugin-vue-devtools";
import tailwindcss from "@tailwindcss/vite";
import AutoImport from "unplugin-auto-import/vite";
import Components from "unplugin-vue-components/vite";
import { PrimeVueResolver } from "@primevue/auto-import-resolver";
import Icons from "unplugin-icons/vite";
import IconsResolver from "unplugin-icons/resolver";
import { VitePWA } from "vite-plugin-pwa";

// https://vite.dev/config/
// `.env.local` con VITE_PWA_DEV=true: service worker y manifest también en `npm run dev`.
const pwaEnDesarrollo = loadEnv("development", process.cwd(), "").VITE_PWA_DEV === "true";
// Revisión de la shell precacheada ("/"): cambia en cada build, así el service worker se actualiza.
const BUILD_ID = Date.now().toString();

export default defineConfig({
  plugins: [
    vue(),
    // vueDevTools(),
    tailwindcss(),
    AutoImport({
      // Solo las APIs de Vue/Router/Pinia: el código del proyecto se importa
      // explícitamente para que cada archivo declare de qué depende.
      imports: ["vue", "vue-router", "pinia"],
      dts: "src/auto-imports.d.ts",
      vueTemplate: true,
    }),
    Components({
      // Solo el kit de UI compartido se registra global (<icon>, <PageHead>…);
      // los componentes de features se importan explícitamente.
      dirs: ["src/shared/ui", "src/shared/icons"],
      dts: "src/components.d.ts",
      resolvers: [
        PrimeVueResolver(),
        IconsResolver({
          prefix: "icon", // Prefix for your components (e.g., <icon-lucide-home />)
          enabledCollections: ["lucide", "material-symbols"],
        }),
      ],
    }),
    Icons({
      compiler: "vue3",
      autoInstall: true,
    }),
    // App instalable (PWA): toda la app del personal; se abre en el chat.
    VitePWA({
      // La versión nueva espera a que el usuario la acepte (`ActualizacionApp`): recargar solo cortaría una venta.
      registerType: "prompt",
      injectRegister: false,
      // En `npm run dev` no hay service worker ni manifest, salvo con VITE_PWA_DEV=true
      // en `.env.local`: para instalar la app de desarrollo en un teléfono de la red local.
      // Las navegaciones van a la red: Caddy manda `/index.html` al backend (la doc de API Platform), no a Vite.
      devOptions: { enabled: pwaEnDesarrollo, navigateFallback: "index.html", navigateFallbackAllowlist: [/^$/] },
      manifest: {
        name: "Fuentes del Norte",
        short_name: "FDN",
        description: "Venta de boletos, salidas y mensajes del personal de Transportes Fuentes del Norte.",
        lang: "es",
        // Abre en el chat, como una app de mensajería; «Volver a la app» lleva al resto.
        // `id` fijo: sin él se deriva de `start_url` y cambiarla haría de la instalada otra app.
        id: "/",
        start_url: "/chat",
        scope: "/",
        display: "standalone",
        theme_color: "#0f172a",
        background_color: "#f8fafc",
        icons: [
          { src: "/pwa/icono-192.png", sizes: "192x192", type: "image/png" },
          { src: "/pwa/icono-512.png", sizes: "512x512", type: "image/png" },
          { src: "/pwa/icono-maskable-512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
        ],
        shortcuts: [
          { name: "Inicio", url: "/" },
          { name: "Venta", url: "/venta" },
          { name: "Boletos", url: "/boletos" },
          { name: "Salidas", url: "/salidas" },
        ],
      },
      workbox: {
        // Solo el código de la app. Las respuestas de la API no se guardan: una taquilla
        // la usan varios usuarios y la caché del service worker no distingue entre ellos.
        // El HTML no se precachea como `index.html`: Caddy manda `/index*` al backend (la doc de
        // API Platform) y esa respuesta quedaría como shell offline en cada navegador tras cada despliegue.
        globPatterns: ["**/*.{js,css,woff2}", "favicon.ico", "pwa/*.png"],
        // La shell se pide a "/", que Caddy sirve desde el frontend (`frontend/server.ts`).
        additionalManifestEntries: [{ url: "/", revision: BUILD_ID }],
        // El catálogo de íconos (8 MB) solo lo usa el selector de íconos: se baja cuando se abre.
        globIgnores: ["**/material-symbols-*.js"],
        maximumFileSizeToCacheInBytes: 6 * 1024 * 1024,
        cleanupOutdatedCaches: true,
        navigateFallback: "/",
        directoryIndex: "",
        // Lo que sirve el backend (Caddy) y no la SPA.
        navigateFallbackDenylist: [/^\/(api|graphql|pagina|docs|bundles|contexts|_profiler|_wdt|\.well-known|auth|logout|index)/],
        // Notificaciones del chat con la app cerrada (Web Push).
        importScripts: ["/push-sw.js"],
      },
    }),
  ],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  server: {
    host: "0.0.0.0",
    port: 9000,
    // open: false
    // Uncomment when the Symfony + API Platform backend is available locally.
    // proxy: {
    //   '/api': {
    //     target: 'http://localhost:8000',
    //     changeOrigin: true
    //   }
    // }
  },
});
