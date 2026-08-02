import "./assets/main.css";
import "virtual:uno.css";
import { createApp } from "vue";

import App from "./App.vue";
import router from "./router";

import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import { createPinia } from "pinia";

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);
const app = createApp(App);

app.use(pinia);
app.use(router);
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      // darkModeSelector: 'html[data-mode="dark"]',
      darkModeSelector: ".dark",
    },
  },
});

app.mount("#app");
