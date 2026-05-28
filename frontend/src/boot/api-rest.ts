// src/boot/api.ts
import { createApi, setApi } from "@/composables/useApiRest";
import { config } from "@/config/config";
import { LoadingBar } from "quasar";
import { boot } from "quasar/wrappers";

export default boot(({ app, router }) => {
  const api: ReturnType<typeof createApi> = createApi({
    baseURL: config.ENTRYPOINT,

    getAccessToken: () => localStorage.getItem("token"),

    refreshToken: async () => {
      const r = await fetch("/auth/refresh");
      if (!r.ok) return null;
      const d = await r.json();
      localStorage.setItem("token", d.token);
      return d.token;
    },

    onStart: () => LoadingBar.start(),
    onEnd: () => LoadingBar.stop(),
  });
  // cancelar requests al cambiar de ruta
  router.beforeEach(() => {
    api.cancelAll();
  });
  setApi(api);
  // app.config.globalProperties.$api = api;
});
declare module "@vue/runtime-core" {
  interface ComponentCustomProperties {
    $api: ReturnType<typeof createApi>;
  }
}
