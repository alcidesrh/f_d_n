// import "./assets/tailwind.css";
import "./assets/main.css";
// import "virtual:uno.css";
import { createApp } from "vue";

import App from "./App.vue";
import router from "./router";

import PrimeVue from "primevue/config";
import { defaultConfig as formkitDefaultConfig, plugin as formkitPlugin } from "@formkit/vue";
import piniaPluginPersistedstate from "pinia-plugin-persistedstate";
import { createPinia } from "pinia";
import formkitConfig from "@/formkit.config";

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);
const app = createApp(App);

app.use(pinia);
app.use(router);
app.use(formkitPlugin, formkitDefaultConfig(formkitConfig()));
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
