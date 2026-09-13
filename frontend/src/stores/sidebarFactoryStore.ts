import { defineStore } from "pinia";
import type { StoreDefinition } from "pinia";
import type { SidebarStoreState } from "./entities/types";
import { gsap } from "gsap";

interface SidebarStore extends StoreDefinition {
  side: string;
  mode: string;
  prevMode: string;
  open: number;
  mini: number;
  close: number;
}
const definitions = new Map<string, StoreDefinition>();

export function defineSidebarStore(side: "left" | "right", name?: string): SidebarStore {
  let definition = definitions.get(side);
  if (!definition) {
    definition = createSidebarStore(side);

    definitions.set(side, definition);
  }
  return definition;
}

function createSidebarStore(side: string, name?: string): StoreDefinition {
  if (name) {
    name = `${name}SidebarStore`;
  } else {
    name = `${side}SidebarStore`;
  }
  return defineStore(name, {
    persist: true,
    state: (): SidebarStoreState => ({
      side: side,
      mode: "open",
      prevMode: "mini",
      open: 250,
      mini: 71,
      close: 0,
    }),
    getters: {
      width: (s: SidebarStoreState): number =>
        ({ open: s.open, mini: s.mini, close: s.close })[s.mode],
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
      handleMouseLeave(e) {
        if (this.mode === "mini") {
          const to = {
            border: "none",
            borderRadius: "none",
            ease: "power1.out",
            zIndex: 999,
            width: "auto",
            duration: 0.4,
            boxShadow: "none",
          };
          if (this.side == "left") {
            gsap.to(e.target, to);
          } else {
            to.x = 0;
            gsap.to(e.target, to);
          }
        } else if (this.mode === "open") {
          // gsap.to(link, { backgroundColor: bg });
        }
      },
      handleMouseEnter(e) {
        const rootStyles = window.getComputedStyle(document.documentElement);
        const shadow = rootStyles.getPropertyValue("--p-surface-300");

        if (this.mode === "mini") {
          const temp = {
            borderRadius: "0 8px 8px 0",
            duration: 0.25,
            ease: "power1.out",
            zIndex: 999,
            width: "0px",
          };
          if (this.side == "left") {
            gsap.fromTo(
              e.target,
              { ...temp },
              { duration: 0.4, width: 200, boxShadow: `1px 0px 3px ${shadow}` },
            );
          } else {
            temp.flexDirection = "row-reverse";
            gsap.fromTo(
              e.target,
              {
                ...temp,
                borderRadius: "8 0px 0px 8",
                display: "flex",
                width: 200,
                flexDirection: "row-reverse",
                x: -130,
                justifyContent: "end",
              },
              {
                duration: 0.4,

                // x: -130,
                boxShadow: `-1px 0px 3px ${shadow}`,
              },
            );
          }

          gsap.fromTo(
            e.currentTarget.querySelector(".menu-text"),
            { opacity: 1, width: "0px", overflow: "hidden" },
            { width: "100%", duration: 0.4 },
          );
        }
      },
      sidebarUpdate() {
        const targets = {
          sidebar: `.sidebar.${this.side}`,
          main: `.main`,
          menu: `.sidebar.${this.side} .menu-text`,
        };
        const duration = 0.3;
        const ease = "";
        // const ease = "expoScale(0.5,7, none)";

        if (this.mode === "open") {
          gsap.to(targets.sidebar, { width: this.width, duration, ease });
          if (this.side == "left") {
            gsap.to(targets.main, { marginLeft: this.width, duration, ease: ease });
          } else {
            gsap.to(targets.main, { marginRight: this.width, duration, ease });
          }
          gsap.to(targets.menu, { opacity: 1, duration: duration * 0.8, ease });
        } else if (this.mode === "mini") {
          gsap.to(targets.sidebar, { width: this.width, overflow: "visible", duration, ease });

          if (this.side == "left") {
            gsap.to(targets.main, { marginLeft: this.width, duration, ease });
          } else {
            gsap.to(targets.main, { marginRight: this.width, duration, ease });
          }
          gsap.to(targets.menu, { opacity: 0, duration: duration * 0.5, ease });
        } else if (this.mode === "close") {
          gsap.to(targets.sidebar, {
            width: this.width,
            opacity: 1,
            overflow: "hidden",
            duration,
            ease,
          });
          if (this.side == "left") {
            gsap.to(targets.main, { marginLeft: 0, duration, ease: ease });
          } else {
            gsap.to(targets.main, { marginRight: 0, duration, ease });
          }
          gsap.to(targets.menu, { opacity: 0, duration: duration * 0.5, ease });
        }
      },
    },
  });
}
