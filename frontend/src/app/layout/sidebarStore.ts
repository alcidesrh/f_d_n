/**
 * Stores de los paneles laterales (`left`, `right` o uno por panel de ruta).
 *
 * Desde `lg` el panel es fijo y empuja el contenido: modo `open`/`mini`/`close`
 * (persistido). Debajo de `lg` es un drawer sobre el contenido: `drawer`
 * (no persistido, siempre empieza cerrado). El modo de escritorio no se toca
 * desde el móvil y viceversa.
 */
import { defineStore } from "pinia";
import { gsap } from "gsap";
import { useUiStore } from "@/app/ui";
import { closeFlyout, openFlyout, resetFlyouts } from "./sidebarFlyout";

export type SidebarMode = "open" | "mini" | "close";

export interface SidebarStoreState {
  side: "left" | "right";
  mode: SidebarMode;
  prevMode: SidebarMode;
  open: number;
  mini: number;
  close: number;
  /** Drawer abierto (solo debajo de `lg`). */
  drawer: boolean;
}

function createDefinition(side: "left" | "right", name: string) {
  return defineStore(name, {
    persist: { omit: ["drawer"] },
    state: (): SidebarStoreState => ({
      side: side,
      mode: "open",
      prevMode: "mini",
      open: 250,
      mini: 71,
      close: 0,
      drawer: false,
    }),
    getters: {
      width: (s: SidebarStoreState): number => ({ open: s.open, mini: s.mini, close: s.close })[s.mode],
      /** Solo íconos: `mini` en escritorio (el drawer siempre muestra los textos). */
      collapsed: (s: SidebarStoreState): boolean => s.mode === "mini" && !useUiStore().isMobile,
    },
    actions: {
      /** Botón de menú de la cabecera: abre/cierra el drawer o alterna el modo de escritorio. */
      toggle() {
        if (useUiStore().isMobile) this.drawer = !this.drawer;
        else this.setMode();
      },
      /** Botón cerrar del panel. */
      dismiss() {
        if (useUiStore().isMobile) this.drawer = false;
        else this.setMode("close");
      },
      setMode(mode?: SidebarMode) {
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
      /** En `mini`, el enlace se despliega mostrando su texto mientras dure el hover. */
      handleMouseEnter(e: MouseEvent) {
        if (!this.collapsed) return;
        openFlyout(e.currentTarget as HTMLElement, {
          side: this.side,
          mini: this.mini,
          open: this.open,
        });
      },
      handleMouseLeave(e: MouseEvent) {
        closeFlyout(e.currentTarget as HTMLElement);
      },
      /**
       * Anima el ancho del panel. Solo escribe variables CSS (`--sb-<lado>-w`
       * y `--sb-<lado>-open`, ver `assets/tokens.css`); `sidebar.css` y
       * `content.css` deciden por breakpoint cómo usarlas (empujar el
       * contenido en escritorio, drawer en móvil). Los textos en `mini` se
       * ocultan por CSS (`.sidebar.mini .menu-text`).
       */
      sidebarUpdate() {
        resetFlyouts(this.side);
        const root = document.documentElement;
        gsap.set(root, { [`--sb-${this.side}-open`]: `${this.open}px` });
        gsap.to(root, {
          [`--sb-${this.side}-w`]: `${this.width}px`,
          duration: 0.3,
          ease: "expoScale(0.5,7, none)",
        });
      },
    },
  });
}

type SidebarDefinition = ReturnType<typeof createDefinition>;
export type SidebarStore = ReturnType<SidebarDefinition>;

const definitions = new Map<string, SidebarDefinition>();

/** Definición (cacheada: Pinia no admite ids repetidos) del store del panel `name`. */
export function defineSidebarStore(side: "left" | "right", name: string = side): SidebarDefinition {
  let definition = definitions.get(name);
  if (!definition) {
    definition = createDefinition(side, name);
    definitions.set(name, definition);
  }
  return definition;
}
