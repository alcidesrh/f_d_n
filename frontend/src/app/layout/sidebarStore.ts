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
    // Solo el modo: los anchos son constantes (un valor viejo persistido pisaría el nuevo).
    persist: { pick: ["mode", "prevMode"] },
    state: (): SidebarStoreState => ({
      side: side,
      mode: "open",
      prevMode: "mini",
      open: 250,
      mini: 60,
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
      /**
       * Anima el ancho del panel. Solo escribe variables CSS (`--sb-<lado>-w`
       * y `--sb-<lado>-open`, ver `assets/tokens.css`); `sidebar.css` y
       * `content.css` deciden por breakpoint cómo usarlas (empujar el
       * contenido en escritorio, drawer en móvil). En `mini` los textos se
       * ocultan y el enlace con hover se despliega, todo por CSS (`sidebar.css`).
       *
       * `--sb-<lado>-w` no se hereda: se anima sobre el panel y sobre `.main`
       * (no en `:root`, que recalcularía el estilo de toda la página en cada
       * fotograma).
       */
      sidebarUpdate() {
        const panel = document.querySelector<HTMLElement>(`.sidebar.${this.side}`);
        const main = document.querySelector<HTMLElement>(".main");
        if (!panel || !main) return;
        const name = `--sb-${this.side}-w`;
        const current = widths[this.side];
        panel.style.setProperty(`--sb-${this.side}-open`, `${this.open}px`);
        // Un panel recién montado (p. ej. el de una ruta) arranca del ancho actual.
        panel.style.setProperty(name, `${current.px}px`);
        // Se anima un número y se escribe en `onUpdate`: GSAP no lee estilos del DOM
        // (leerlos al arrancar forzaba un recálculo de estilo en el primer fotograma).
        gsap.to(current, {
          px: this.width,
          duration: 0.3,
          ease: "power1.out",
          overwrite: true,
          onUpdate: () => {
            panel.style.setProperty(name, `${current.px}px`);
            main.style.setProperty(name, `${current.px}px`);
          },
        });
      },
    },
  });
}

/** Ancho animado actual de cada lado (px); arranca en el `initial-value` de `--sb-<lado>-w`. */
const widths: Record<"left" | "right", { px: number }> = { left: { px: 250 }, right: { px: 250 } };

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
