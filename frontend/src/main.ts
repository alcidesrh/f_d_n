/**
 * Arranque de la aplicación: plugins → tema → schema GraphQL → montaje.
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
import "./assets/main.css";
import { pinia } from "@/app/pinia";
import { router } from "@/app/router";
import formkitConfig from "@/formkit.config";
import { useUiStore } from "@/app/ui";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { syncVueRoutes } from "@/utils/vueRoutesSync";

async function bootstrap() {
  const app = createApp(App)
    .use(pinia)
    .use(router)
    .use(formkitPlugin, formkitDefaultConfig(formkitConfig()))
    .use(PrimeVue, { locale: es, theme: { options: { darkModeSelector: ".darks" } } })
    .use(DialogService)
    .use(ConfirmationService)
    .use(ToastService);

  await useUiStore().init();
  await useSchemaRepositoryStore().init();
  app.mount("#app");

  void syncVueRoutes().then((result) => {
    if (!result.ok) console.warn("[vueRoutes] no se pudo sincronizar rutas:", result.error);
  });
}

void bootstrap();
