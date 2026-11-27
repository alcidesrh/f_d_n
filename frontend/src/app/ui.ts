import { defineStore } from "pinia";
import { mediaUp } from "./breakpoints";

/** Desde `lg` el shell tiene sidebars fijos; debajo, drawers. */
const DESKTOP_QUERY = mediaUp("lg");

function isBelowDesktop(): boolean {
  if (typeof window === "undefined" || typeof window.matchMedia !== "function") return false;
  return !window.matchMedia(DESKTOP_QUERY).matches;
}

export interface UiState {
  /** Viewport debajo de `lg`: navegación en drawers (ver `app/breakpoints.ts`). */
  isMobile: boolean;
}

/**
 * Viewport del shell: si la navegación va en drawers (debajo de `lg`). Es del
 * navegador, no del usuario (`global`). La apariencia (modo, colores, letra…)
 * está en `app/apariencia/store.ts`; los paneles laterales, en
 * `layout/sidebarStore.ts`.
 */
export const useUiStore = defineStore("ui", {
  global: true,
  state: (): UiState => ({
    isMobile: isBelowDesktop(),
  }),

  actions: {
    syncViewport() {
      this.isMobile = isBelowDesktop();
    },
    /** Sigue el cruce de `lg` (no cada resize). Devuelve la función para dejar de seguirlo. */
    watchViewport(): () => void {
      this.syncViewport();
      if (typeof window === "undefined" || typeof window.matchMedia !== "function") return () => {};
      const query = window.matchMedia(DESKTOP_QUERY);
      const sync = () => this.syncViewport();
      query.addEventListener("change", sync);
      return () => query.removeEventListener("change", sync);
    },
  },
});
