import { defineStore } from "pinia";
import type { PrimaryColor, SurfacePalette, ThemeMode, ThemePreset } from "./themeTypes";
import { usePreset } from "@primeuix/themes";
import { mediaUp } from "./breakpoints";
import { themeColors } from "./theme";

/** Desde `lg` el shell tiene sidebars fijos; debajo, drawers. */
const DESKTOP_QUERY = mediaUp("lg");

function isBelowDesktop(): boolean {
  if (typeof window === "undefined" || typeof window.matchMedia !== "function") return false;
  return !window.matchMedia(DESKTOP_QUERY).matches;
}

export interface UiState {
  mode: ThemeMode;
  primary: PrimaryColor;
  surface: SurfacePalette;
  preset: ThemePreset;
  /** Viewport debajo de `lg`: navegación en drawers (ver `app/breakpoints.ts`). */
  isMobile: boolean;
}

/**
 * Preferencias de interfaz (tema: modo, preset, color primario y superficie)
 * y viewport móvil. Los paneles laterales tienen su propio store
 * (`layout/sidebarStore.ts`).
 */
export const useUiStore = defineStore("ui", {
  persist: { omit: ["isMobile"] },
  state: (): UiState => ({
    mode: "light",
    primary: "blue",
    surface: "slate",
    preset: "lara",
    isMobile: isBelowDesktop(),
  }),

  actions: {
    setMode(mode: ThemeMode) {
      this.mode = mode;
      this.applyTheme();
    },
    setPrimary(primary: PrimaryColor) {
      this.primary = primary;
      this.applyTheme();
    },
    setSurface(surface: SurfacePalette) {
      this.surface = surface;
      this.applyTheme();
    },
    setPreset(preset: ThemePreset) {
      this.preset = preset;
      this.applyTheme();
    },
    applyTheme() {
      usePreset(themeColors(this.preset, this.primary, this.surface, this.mode));
    },
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
    init() {
      this.syncViewport();
      return this.applyTheme();
    },
  },
});
