import App from "./App.vue";
import router from "./router";
// CSS-------------------
import "./assets/main.css";
// import "virtual:uno.css";
import { createApp } from "vue";

//Primevue-------------------
import PrimeVue from "primevue/config";
import { es } from "primelocale/js/es.js";
import DialogService from "primevue/dialogservice";
import ConfirmationService from "primevue/confirmationservice";
import ToastService from "primevue/toastservice";

// Formkit----------------------
import { defaultConfig as formkitDefaultConfig, plugin as formkitPlugin } from "@formkit/vue";
import formkitConfig from "@/formkit.config";

// Pinia--------------------
import { pinia } from "@/stores/pinia.ts";
import { initGlobalStores } from "@/stores/global.ts";

// Apollo------------------------
// Cliente GraphQL singleton (se crea al importar; acceso global vía `apollo`).
import "@/lib/apollo";

async function bootstrap() {
  const app = createApp(App);
  app.use(pinia);
  app.use(router);
  app.use(formkitPlugin, formkitDefaultConfig(formkitConfig()));
  app.use(PrimeVue, {
    locale: es,
    theme: {
      options: {
        darkModeSelector: ".darks",
      },
    },
  });
  app.use(DialogService);
  app.use(ConfirmationService);
  app.use(ToastService);

  await initGlobalStores();

  app.mount("#app");
}

bootstrap();
