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
  leftState: PanelState;
  rightState: PanelState;
  isMobile: boolean;
  mobileLeftOpen: boolean;
  mobileRightOpen: boolean;
  prevLeftState: PanelState;
  prevRightState: PanelState;
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
    leftState: "open",
    prevLeftState: "open",
    prevRightState: "open",
    rightState: "open",
    isMobile: typeof window !== "undefined" ? window.innerWidth <= MOBILE_BREAKPOINT : false,
    mobileLeftOpen: false,
    mobileRightOpen: false,
  }),

  getters: {
    leftWidth: (state): string => ({ open: "250px", mini: "70px", close: "0px" })[state.leftState],
    rightWidth: (state): string =>
      ({ open: "250px", mini: "70px", close: "0px" })[state.rightState],
  },

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
    /** Cycles open -> mini -> close -> open. On mobile it toggles the overlay drawer instead. */
    cycleLeft() {
      if (this.isMobile) {
        this.mobileLeftOpen = !this.mobileLeftOpen;
        return;
      }
      if (this.leftState == "close") {
        this.leftState = this.prevLeftState;
        this.prevLeftState = "close";
      } else {
        this.leftState =
          this.leftState === "open" ? "mini" : this.leftState === "mini" ? "close" : "open";
      }
    },
    cycleRight() {
      if (this.isMobile) {
        this.mobileRightOpen = !this.mobileRightOpen;
        return;
      }
      if (this.rightState == "close") {
        this.rightState = this.prevRightState;
      } else {
        this.rightState =
          this.rightState === "open" ? "mini" : this.rightState === "mini" ? "close" : "open";
      }
    },
    closeMobileOverlays() {
      if (this.isMobile) {
        this.mobileLeftOpen = false;
        this.mobileRightOpen = false;
      }
    },
    syncViewport() {
      const mobile = window.innerWidth <= MOBILE_BREAKPOINT;
      this.isMobile = mobile;
      if (!mobile) {
        this.mobileLeftOpen = false;
        this.mobileRightOpen = false;
      }
    },
    init() {
      this.syncViewport();
      this.applyTheme();
    },
  },
});
