import "vue-router";
import type { Component } from "vue";

declare module "vue-router" {
  interface RouteMeta {
    title?: string;
    /** `blank` = pantalla completa sin shell (login, 404). Por defecto, el shell de la app. */
    layout?: "blank";
    /** Rutas accesibles sin sesión. */
    public?: boolean;
    icon?: string;
    label?: string;
    /** Panel lateral derecho propio de la ruta (reemplaza al menú derecho). */
    panel?: { component: () => Promise<Component | { default: Component }>; width?: number };
  }
}
