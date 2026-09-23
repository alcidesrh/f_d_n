import { setActivePinia } from "pinia";
import { pinia } from "./stores/pinia";
import { useUiStore } from "@/stores/ui";
export { defineSidebarStore } from "@/stores/sidebarFactoryStore";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { useUserSessionStore } from "@/stores/session";
import { syncVueRoutes } from "@/utils/vueRoutesSync";
import { createApi, setApi } from "@/lib/useApiRest";
import { useLoadingStore } from "@/stores/loadingStore";
import { createApiPlatformClient } from "@/lib/apollo/client";
// import "@/lib/apollo";

export let ui: ReturnType<typeof useUiStore>;
export let apiGraphql: ReturnType<typeof useSchemaRepositoryStore>;
export let session: ReturnType<typeof useUserSessionStore>;
export let apiRest: ReturnType<typeof createApi>;
export let loadingStore: ReturnType<typeof useLoadingStore>;
export let apollo: ReturnType<typeof createApiPlatformClient>;

export async function init() {
  setActivePinia(pinia); // necesario para usar la store fuera de un componente
  ui = useUiStore();
  await ui.init();
  loadingStore = useLoadingStore();
  session = useUserSessionStore();
  apollo = createApiPlatformClient();
  apiGraphql = useSchemaRepositoryStore();
  apiRest = createApi({
    baseURL: import.meta.env.VITE_REST_ENDPOINT,

    getAccessToken: () => session.token, //localStorage.getItem('token'),

    // refreshToken: async () => {
    // 	const r = await restApi.get('/auth/refresh')
    // 	if (!r.ok) return null
    // 	const d = await r.json()
    // 	localStorage.setItem('token', d.token)
    // 	return d.token
    // },

    onStart: (key?) => {
      loadingStore.start(key || "anonymous");
    },
    onEnd: (key?) => {
      loadingStore.stop(key || "anonymous");
    },
  });
  try {
    await apiGraphql.init();
  } catch (error) {
    console.error("[apiGraphql] falló la carga del schema:", error);
  }

  // Sincroniza las rutas del router con la entidad VueRoute del backend.
  // Se lanza sin bloquear; también puede dispararse a voluntad llamando a
  // `syncVueRoutes()` desde cualquier módulo.
  syncVueRoutes().then((result) => {
    if (!result.ok) {
      console.warn("[vueRoutes] no se pudo sincronizar rutas:", result.error);
    }
  });
}
