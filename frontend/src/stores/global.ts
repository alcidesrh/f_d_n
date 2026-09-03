import { setActivePinia } from "pinia";
import { pinia } from "./pinia";
import { useUiStore } from "@/stores/ui";
export { defineSidebarStore } from "@/stores/sidebarFactoryStore";
import { useSchemaRepositoryStore } from "@/stores/schemaRepository";
import { useMenusStore } from "@/stores/menus";
import { syncVueRoutes } from "@/utils/vueRoutesSync";

export let ui: ReturnType<typeof useUiStore>;
export let schemaRepository: ReturnType<typeof useSchemaRepositoryStore>;
export let menus: ReturnType<typeof useMenusStore>;

export async function initGlobalStores() {
  setActivePinia(pinia); // necesario para usar la store fuera de un componente
  ui = useUiStore();
  await ui.init();

  // Punto de entrada a la API GraphQL: se crea después del cliente Apollo
  // (src/lib/apollo/client.ts) y antes de montar cualquier vista. En la
  // primera creación parsea la introspección GraphQL; luego queda persistido.
  schemaRepository = useSchemaRepositoryStore();
  try {
    await schemaRepository.init();
  } catch (error) {
    console.error("[schemaRepository] falló la carga del schema:", error);
  }

  // Menús por área de layout: se carga en background (no bloquea el bootstrap).
  menus = useMenusStore();
  if (!menus.fetched) {
    menus.fetchMenusByArea().catch((e) => {
      console.error("[menus] falló la carga de menús:", e);
    });
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
