import { setActivePinia } from "pinia";
import { pinia } from "./pinia";
import { useUiStore } from "@/stores/ui";

export let ui: ReturnType<typeof useUiStore>;

export async function initGlobalStores() {
  setActivePinia(pinia); // necesario para usar la store fuera de un componente
  ui = useUiStore();
  await ui.init();
}
