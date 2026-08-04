// import "./assets/tailwind.css";
import "./assets/main.css";
// import "virtual:uno.css";
import { createApp } from "vue";

import App from "./App.vue";
import router from "./router";

import PrimeVue from "primevue/config";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import { createPinia } from "pinia";

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);
const app = createApp(App);

app.use(pinia);
app.use(router);
app.use(PrimeVue, {
  theme: {
    options: {
      cssLayer: {
        name: "primevue",
        order: "theme, base, primevue",
      },
      darkModeSelector: ".dark2",
    },
  },
});

app.mount("#app");
