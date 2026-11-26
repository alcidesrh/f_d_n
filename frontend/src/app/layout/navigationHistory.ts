import { defineStore } from "pinia";
import type { RouteLocationNormalized, RouteParams } from "vue-router";

export interface BreadcrumbEntry {
  label: string;
  path: string;
  name?: string | symbol;
  params?: RouteParams;
  icon?: string;
}

export interface NavigationHistoryState {
  entries: BreadcrumbEntry[];
}

const MAX_CRUMBS = 4;

export const useNavigationHistoryStore = defineStore("navigationHistory", {
  state: (): NavigationHistoryState => ({
    entries: [],
  }),

  actions: {
    push(route: RouteLocationNormalized) {
      const label = (route.meta.label as string | undefined) || (route.name ? String(route.name) : route.path);

      // Un mismo texto no se repite: lo que ya está (otra sección de la misma pantalla, p. ej. "Mi cuenta" con `?seccion=`)
      // se quita y vuelve al final con la ruta actual, así que la migaja siempre lleva a donde se está.
      this.entries = this.entries.filter((entry) => entry.label !== label);

      this.entries.push({
        label,
        path: route.fullPath,
        name: route.name ?? undefined,
        params: { ...route.params },
        icon: route.meta?.icon as string | undefined,
      });

      if (this.entries.length > MAX_CRUMBS) {
        this.entries = this.entries.slice(-MAX_CRUMBS);
      }
    },
  },
});
