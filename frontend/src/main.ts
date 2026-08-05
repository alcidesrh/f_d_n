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

import { VueQueryPlugin } from '@tanstack/vue-query';
import { createGraphQLOrm } from '@graphql-orm/vue';
import { LiveIntrospectionSource, SdlSnapshotSource } from '@graphql-orm/core';

const isProd = import.meta.env.PROD;
const graphqlEndpoint = import.meta.env.VITE_GRAPHQL_ENDPOINT || 'http://localhost/graphql';

const orm = createGraphQLOrm({
  endpoint: graphqlEndpoint,
  source: isProd
    ? new SdlSnapshotSource('/schema.graphql')
    : new LiveIntrospectionSource(graphqlEndpoint),
  entities: ['Status', 'Empresa', 'Piloto', 'Trayecto', 'Servicio', 'Usuario'],
});

app.use(pinia);
app.use(router);
app.use(VueQueryPlugin);
app.use(orm);
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
