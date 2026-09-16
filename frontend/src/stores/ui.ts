import { defineStore } from "pinia";
import type { PanelState, PrimaryColor, SurfacePalette, ThemeMode, ThemePreset } from "@/types";
import {
  usePreset,
  updatePrimaryPalette,
  updateSurfacePalette,
  updatePreset,
} from "@primeuix/themes";
import { invertPalette, PRESET_OPTIONS, componentsPreset, themeColors } from "@/config/theme";
// import colors from "tailwindcss/colors";

const MOBILE_BREAKPOINT = 1024;

export interface UiState {
  mode: ThemeMode;
  primary: PrimaryColor;
  surface: SurfacePalette;
  preset: ThemePreset;
  isMobile: boolean;
}

/**
 * Global UI store: theme (mode / primary / surface) and the tri-state
 * left & right panels. Kept separate from domain stores (fleet, routes,
 * tickets, etc.) so it can be persisted independently later (e.g. to a
 * user-preferences endpoint) without touching business data.
 */
export const useUiStore = defineStore("ui", {
  persist: true,
  state: (): UiState => ({
    mode: "light",
    primary: "blue",
    surface: "slate",
    preset: "lara",
    isMobile: typeof window !== "undefined" ? window.innerWidth <= MOBILE_BREAKPOINT : false,
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
    async applyTheme() {
      await usePreset(themeColors(this.preset, this.primary, this.surface, this.mode));
    },
    setLeft(state: PanelState) {
      this.prevLeftState = this.leftState;
      this.leftState = state;
    },
    setRight(state: PanelState) {
      this.prevRightState = this.rightState;
      this.rightState = state;
    },
    syncViewport() {
      const mobile = window.innerWidth <= MOBILE_BREAKPOINT;
      this.isMobile = mobile;
    },
    init() {
      this.syncViewport();
      this.applyTheme();
    },
  },
});
