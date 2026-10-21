/**
 * Arranque de la aplicación: plugins → tema → schema GraphQL (el guardado, si
 * el manifiesto de la metadata dice que sigue vigente; ADR-036) → montaje.
 * La sincronización de rutas con el backend se lanza sin bloquear.
 */
import { createApp } from "vue";
import PrimeVue from "primevue/config";
import ConfirmationService from "primevue/confirmationservice";
import DialogService from "primevue/dialogservice";
import ToastService from "primevue/toastservice";
import { es } from "primelocale/js/es.js";
import { defaultConfig as formkitDefaultConfig, plugin as formkitPlugin } from "@formkit/vue";
import App from "./App.vue";
import "@/assets/main.css";
import { pinia } from "@/app/pinia";
import { router } from "@/app/router";
import formkitConfig from "@/shared/formkit/config";
import { useUiStore } from "@/app/ui";
import { useMetadataStore } from "@/core/metadata/sincronizacion";
import { syncVueRoutes } from "@/app/routeSync";
import { escucharInstalacion } from "@/app/pwa";

// Antes de montar: el aviso de instalación del navegador llega pronto y una sola vez.
escucharInstalacion();

async function bootstrap() {
  const app = createApp(App)
    .use(pinia)
    .use(router)
    .use(formkitPlugin, formkitDefaultConfig(formkitConfig()))
    // Sin modo oscuro propio de PrimeVue: el oscuro sale de invertir las rampas (app/theme.ts).
    .use(PrimeVue, { locale: es, theme: { options: { darkModeSelector: false } } })
    .use(DialogService)
    .use(ConfirmationService)
    .use(ToastService);

  const uiStore = useUiStore();
  await uiStore.init();
  await useMetadataStore().arrancar();
  // Sin esto se monta con la ruta inicial sin resolver (sin `meta.layout`) y parpadea el layout de la app antes del de /login.
  await router.isReady();
  app.mount("#app");

  void syncVueRoutes().then((result) => {
    if (!result.ok) console.warn("[vueRoutes] no se pudo sincronizar rutas:", result.error);
  });
}

void bootstrap();
