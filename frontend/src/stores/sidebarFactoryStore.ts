import { defineStore } from "pinia";
import type { StoreDefinition } from "pinia";
import type { SidebarStoreState, SidebarStore } from "./entities/types";
import { gsap } from "gsap";

const definitions = new Map<string, StoreDefinition>();

export function defineSidebarStore(side: "left" | "right", name?: string): SidebarStore {
  name = name ?? side;
  let definition = definitions.get(name);
  if (!definition) {
    definition = defineStore(name, {
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
        width: (s: SidebarStoreState): number => ({ open: s.open, mini: s.mini, close: s.close })[s.mode],
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
                {
                  duration: 0.4,
                  width: 200,
                  boxShadow: `1px 0px 3px ${shadow}`,
                },
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

            gsap.fromTo(e.currentTarget.querySelector(".menu-text"), { opacity: 1, width: "0px", overflow: "hidden" }, { width: "100%", duration: 0.4 });
          }
        },
        handleMouseLeave2(e: MouseEvent) {
          const link = e.currentTarget as HTMLElement;
          const text = link.querySelector(".menu-text");
          if (!link.dataset.expanded) return;
          const hidden = link.offsetWidth - this.mini;
          const reset = () => {
            delete link.dataset.expanded;
            gsap.set(link, { clearProps: "width,x,clipPath,boxShadow,borderRadius,flexDirection" });
            gsap.set(link.parentElement, { clearProps: "zIndex" });
            if (text) gsap.set(text, { clearProps: "marginLeft,marginRight" });
          };

          gsap.killTweensOf([link, text]);
          // Si el modo cambió mientras estaba expandido, sidebarUpdate ya gestiona la opacidad del texto.
          if (this.mode !== "mini") return reset();
          gsap.to(text, { opacity: 0, duration: 0.15, ease: "power1.out" });
          gsap.to(link, {
            clipPath: this.side == "left" ? `inset(0px ${hidden}px 0px 0px)` : `inset(0px 0px 0px ${hidden}px)`,
            boxShadow: "0px 0px 0px rgba(0,0,0,0)",
            duration: 0.2,
            ease: "power1.out",
            onComplete: reset,
          });
        },
        /**
         * En modo mini expande el item bajo el cursor para mostrar icono + texto.
         * El ancho final se fija una sola vez (un único layout) y la revelación se
         * anima con `clip-path` + `opacity`, que no provocan re-layout por frame.
         * En la derecha el item crece hacia la izquierda (`x` negativo + `row-reverse`)
         * para que el icono no se mueva de su sitio.
         */
        handleMouseEnter2(e: MouseEvent) {
          if (this.mode !== "mini") return;
          const link = e.currentTarget as HTMLElement;
          const text = link.querySelector<HTMLElement>(".menu-text");
          const shadow = getComputedStyle(document.documentElement).getPropertyValue("--p-surface-300");
          const left = this.side == "left";

          gsap.killTweensOf([link, text]);
          // Re-entrada durante el colapso: se conserva el ancho y el clip-path actual.
          const reentry = !!link.dataset.expanded;
          const expanded = reentry ? link.offsetWidth : Math.max(200, link.scrollWidth);
          const hidden = expanded - this.mini;
          link.dataset.expanded = "1";

          gsap.set(link.parentElement, { zIndex: 10 });
          if (text) gsap.set(text, left ? {} : { marginLeft: 0, marginRight: 15 });
          gsap.set(link, {
            width: expanded,
            x: left ? 0 : -hidden,
            flexDirection: left ? "row" : "row-reverse",
            borderRadius: left ? "0 8px 8px 0" : "8px 0 0 8px",
          });
          if (!reentry) {
            gsap.set(link, {
              clipPath: left ? `inset(0px ${hidden}px 0px 0px)` : `inset(0px 0px 0px ${hidden}px)`,
            });
          }
          gsap.to(link, {
            // Márgenes negativos del lado expandido para que la sombra no quede recortada.
            clipPath: left ? "inset(-6px -6px -6px 0px)" : "inset(-6px 0px -6px -6px)",
            boxShadow: `${left ? 1 : -1}px 0px 3px ${shadow}`,
            duration: 0.25,
            ease: "power2.out",
          });
          gsap.to(text, { opacity: 1, duration: 0.2, delay: 0.05, ease: "power1.out" });
        },
        sidebarUpdate() {
          const targets = {
            sidebar: `.sidebar.${this.side}`,
            main: `.main`,
            menu: `.sidebar.${this.side} .menu-text`,
          };
          const duration = 0.2;
          // const ease = "expoScale(1, 2)";
          const ease = "expoScale(0.5,7, none)";

          if (this.mode === "open") {
            gsap.to(targets.sidebar, { width: this.width, duration, ease });
            if (this.side == "left") {
              gsap.to(targets.main, {
                marginLeft: this.width,
                duration,
                ease: ease,
              });
            } else {
              gsap.to(targets.main, {
                marginRight: this.width,
                duration,
                ease,
              });
            }
            gsap.to(targets.menu, {
              opacity: 1,
              duration: duration * 0.8,
              ease,
            });
          } else if (this.mode === "mini") {
            gsap.to(targets.sidebar, {
              width: this.width,
              overflow: "visible",
              duration,
              ease,
            });

            if (this.side == "left") {
              gsap.to(targets.main, { marginLeft: this.width, duration, ease });
            } else {
              gsap.to(targets.main, {
                marginRight: this.width,
                duration,
                ease,
              });
            }
            gsap.to(targets.menu, {
              opacity: 0,
              duration: duration * 0.5,
              ease,
            });
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
            gsap.to(targets.menu, {
              opacity: 0,
              duration: duration * 0.5,
              ease,
            });
          }
        },
      },
    });
    definitions.set(name, definition);
  }
  return definition as SidebarStore;
}
