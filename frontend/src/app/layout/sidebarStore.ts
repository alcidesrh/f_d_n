/**
 * Stores de los paneles laterales (`left`, `right` o uno por panel de ruta).
 *
 * Desde `lg` el panel es fijo y empuja el contenido: modo `open`/`mini`/`close`
 * (persistido). Debajo de `lg` es un drawer sobre el contenido: `drawer`
 * (no persistido, siempre empieza cerrado). El modo de escritorio no se toca
 * desde el móvil y viceversa.
 */
import { defineStore } from "pinia";
import { useUiStore } from "@/app/ui";

export type SidebarMode = "open" | "mini" | "close";

export interface SidebarStoreState {
  side: "left" | "right";
  mode: SidebarMode;
  prevMode: SidebarMode;
  /**
   * Modo con el que está dibujado el menú. Sigue a `mode`, pero lo mueve
   * `sidebarMotion.ts` en el momento exacto de la animación (no persistido).
   */
  shown: SidebarMode;
  /**
   * Pliegue `open ⇄ mini` en curso (lo mueve `sidebarMotion.ts`, no persistido):
   * los submenús entran y salen sin su propia animación, la lleva el pliegue.
   */
  morphing: boolean;
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
      shown: "open",
      morphing: false,
      open: 250,
      mini: 60,
      close: 0,
      drawer: false,
    }),
    getters: {
      width: (s: SidebarStoreState): number => ({ open: s.open, mini: s.mini, close: s.close })[s.mode],
      /** Menú dibujado solo con íconos: `mini` en escritorio (el drawer siempre muestra los textos). */
      compact: (s: SidebarStoreState): boolean => s.shown === "mini" && !useUiStore().isMobile,
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
