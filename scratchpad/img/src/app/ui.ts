import { defineStore } from "pinia";
import type { PrimaryColor, SurfacePalette, ThemeMode, ThemePreset } from "./themeTypes";
import { usePreset } from "@primeuix/themes";
import { mediaUp } from "./breakpoints";
import { presetCompleto } from "./theme";

/** Desde `lg` el shell tiene sidebars fijos; debajo, drawers. */
const DESKTOP_QUERY = mediaUp("lg");

function isBelowDesktop(): boolean {
  if (typeof window === "undefined" || typeof window.matchMedia !== "function") return false;
  return !window.matchMedia(DESKTOP_QUERY).matches;
}

/** Primera visita (sin preferencia guardada): el modo del sistema operativo. */
function systemMode(): ThemeMode {
  if (typeof window === "undefined" || typeof window.matchMedia !== "function") return "light";
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

export interface UiState {
  mode: ThemeMode;
  primary: PrimaryColor;
  surface: SurfacePalette;
  preset: ThemePreset;
  /** Viewport debajo de `lg`: navegación en drawers (ver `app/breakpoints.ts`). */
  isMobile: boolean;
}

/** Tema de fábrica (sin el modo: ese lo toma del sistema operativo). */
const TEMA_POR_DEFECTO = { primary: "blue", surface: "slate", preset: "lara" } as const satisfies Omit<UiState, "mode" | "isMobile">;

/**
 * Preferencias de interfaz (tema: modo, preset, color primario y superficie)
 * y viewport móvil. Los paneles laterales tienen su propio store
 * (`layout/sidebarStore.ts`).
 */
export const useUiStore = defineStore("ui", {
  // App.vue la usa siempre: al entrar otro usuario se recarga con su tema.
  permanente: { alRecargar: (): void => useUiStore().applyTheme() },
  persist: { omit: ["isMobile"] },
  state: (): UiState => ({
    mode: systemMode(),
    ...TEMA_POR_DEFECTO,
    isMobile: isBelowDesktop(),
  }),

  actions: {
    setMode(mode: ThemeMode) {
      this.mode = mode;
      this.applyTheme();
    },
    toggleMode() {
      this.setMode(this.mode === "dark" ? "light" : "dark");
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
    /** Vuelve a color, superficie y preset de fábrica; el modo claro/oscuro se conserva. */
    restablecerTema() {
      this.primary = TEMA_POR_DEFECTO.primary;
      this.surface = TEMA_POR_DEFECTO.surface;
      this.preset = TEMA_POR_DEFECTO.preset;
      this.applyTheme();
    },
    applyTheme() {
      usePreset(presetCompleto(this.preset, this.primary, this.surface, this.mode));
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
