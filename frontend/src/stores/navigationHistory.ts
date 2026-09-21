import { defineStore } from "pinia";
import type { RouteLocationNormalized, RouteParams } from "vue-router";

export interface BreadcrumbEntry {
  label: string;
  path: string;
  name?: string | symbol;
  params?: RouteParams;
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
      const label =
        (route.meta.label as string | undefined) ||
        (route.name ? String(route.name) : route.path);

      // Si la ruta ya está en el historial, se quita para volver a agregarla al final.
      this.entries = this.entries.filter((entry) => entry.path !== route.fullPath);

      this.entries.push({
        label,
        path: route.fullPath,
        name: route.name ?? undefined,
        params: { ...route.params },
      });

      if (this.entries.length > MAX_CRUMBS) {
        this.entries = this.entries.slice(-MAX_CRUMBS);
      }
    },
  },
});
