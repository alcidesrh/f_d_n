import App from "./App.vue";
import router from "./router";

// CSS-------------------
import "./assets/main.css";
// import "virtual:uno.css";
import { createApp } from "vue";

//Primevue-------------------
import PrimeVue from "primevue/config";
import DialogService from "primevue/dialogservice";

// Formkit----------------------
import { defaultConfig as formkitDefaultConfig, plugin as formkitPlugin } from "@formkit/vue";
import formkitConfig from "@/formkit.config";

// Pinia--------------------
import { pinia } from "@/stores/pinia.ts";
import { initGlobalStores } from "@/stores/global.ts";

// App---------------------------
import { VueQueryPlugin } from "@tanstack/vue-query";

async function bootstrap() {

  const isProd = import.meta.env.PROD;
  const graphqlEndpoint = import.meta.env.VITE_GRAPHQL_ENDPOINT || "http://localhost/graphql";


  const app = createApp(App);
  app.use(pinia);
  app.use(router);
  app.use(VueQueryPlugin);
  app.use(orm);
  app.use(formkitPlugin, formkitDefaultConfig(formkitConfig()));
  app.use(PrimeVue, {
    theme: {
      options: {
      //   // cssLayer: {
      //   //   name: "primevue",
      //   //   order: "theme, base, primevue",
      //   // },
        darkModeSelector: ".dark",
      },
    },
  });
  app.use(DialogService);

  await initGlobalStores();

  app.mount("#app");
}

bootstrap()
