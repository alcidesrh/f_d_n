import { defineStore } from "pinia";
import type { StoreDefinition } from "pinia";
import type { SidebarStoreState } from "./entities/types";

const definitions = new Map<string, StoreDefinition>();

export function defineSidebarStore(side: "left" | "right"): StoreDefinition {
  let definition = definitions.get(side);
  if (!definition) {
    definition = createSidebarStore(side);

    definitions.set(side, definition);
  }
  return definition;
}

function createSidebarStore(side: string): StoreDefinition {
  return defineStore(`${side}SidebarStore`, {
    persist: true,
    state: (): SidebarStoreState => ({
      side: side,
      mode: "open",
      prevMode: "mini",
    }),
    getters: {
      width: (s: SidebarStoreState): number => ({ open: 250, mini: 70, close: 0 })[s.mode],
    },
    actions: {
      setMode(mode?: "open" | "mini" | "close") {
        if (mode && mode != this.mode) {
          this.prevMode = this.mode;
          this.mode = mode;
        } else if (this.prevMode != this.mode) {
          mode = this.mode;
          this.mode = this.prevMode;
          this.prevMode = mode;
        } else {
          this.mode = this.mode == "open" ? "mini" : "open";
          this.prevMode = this.mode == "open" ? "mini" : "open";
        }
      },

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
      },
    },
  });
}
