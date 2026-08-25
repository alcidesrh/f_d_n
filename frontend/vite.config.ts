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
      imports: ["vue", "vue-router", "pinia", { "@/store/globals": ["ui"] }],

      dts: "src/auto-imports.d.ts",

      vueTemplate: true,

      dirs: ["./src/composables", "./src/stores", "./src/utils"],
    }),
    Components({
      dirs: ["src/components"],
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
      compiler: 'vue3',
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
