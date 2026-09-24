import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import vueDevTools from "vite-plugin-vue-devtools";
import tailwindcss from "@tailwindcss/vite";
import AutoImport from "unplugin-auto-import/vite";
import Components from "unplugin-vue-components/vite";
import { PrimeVueResolver } from "@primevue/auto-import-resolver";
import Icons from "unplugin-icons/vite";
import IconsResolver from "unplugin-icons/resolver";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
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
          enabledCollections: ["lucide", "tabler", "material-symbols"], // Turn on both sets
        }),
      ],
    }),
    Icons({
      compiler: "vue3",
      autoInstall: true,
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
