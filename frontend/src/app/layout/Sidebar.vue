<template>
  <aside ref="panel" class="sidebar" :class="[sidebarStore.side, sidebarStore.shown]">
    <div :class="sidebarStore.side" class="sidebar-control">
      <!-- Vista del panel (solo escritorio): cada glifo dibuja la ventana con el panel como quedaría. -->
      <div class="sb-modes" role="radiogroup" aria-label="Vista del panel">
        <button v-for="vista in vistas" :key="vista.modo" type="button" role="radio" class="sb-modes__opt" :class="`sb-modes__opt--${vista.modo}`" :aria-checked="sidebarStore.mode === vista.modo" :aria-label="vista.label" v-tooltip.bottom="vista.label" @click="elegir(vista.modo, $event)">
          <svg class="sb-glyph" viewBox="0 0 20 20" aria-hidden="true">
            <rect x="2.75" y="3.75" width="14.5" height="12.5" rx="3" />
            <template v-if="vista.borde">
              <path class="sb-glyph__panel" :d="`M${vista.borde} 3.75H5.75a3 3 0 0 0-3 3v6.5a3 3 0 0 0 3 3H${vista.borde}z`" />
              <path :d="`M${vista.borde} 3.75v12.5`" />
            </template>
          </svg>
        </button>
      </div>
    </div>
    <nav class="mt-3">
      <slot name="menu-content"> </slot>
    </nav>
  </aside>
</template>
<script setup lang="ts">
import { computed, useTemplateRef } from "vue";
import { defineSidebarStore, type SidebarMode, type SidebarStore } from "./sidebarStore";
import { useSidebarMotion } from "./sidebarMotion";

const props = defineProps<{ side?: "left" | "right"; store?: SidebarStore; nomini?: boolean }>();

const sidebarStore = props.store ?? defineSidebarStore(props.side ?? "left")();
const panel = useTemplateRef<HTMLElement>("panel");

/** Vista del panel. `borde`: hasta dónde llega el panel en el glifo (sin él, oculto). */
type Vista = { modo: SidebarMode; label: string; borde?: number };

const CON_TEXTOS: Vista = { modo: "open", label: "Con textos", borde: 9 };
const SOLO_ICONOS: Vista = { modo: "mini", label: "Solo íconos", borde: 6.25 };
const OCULTO: Vista = { modo: "close", label: "Ocultar panel" };

// De más a menos panel, las que ofrece la estructura dibujada (`shown`): en `mini` no caben más
// que volver a los textos; un panel sin `mini` (el de una ruta) solo se muestra u oculta.
const vistas = computed((): Vista[] => {
  if (props.nomini) return [{ ...CON_TEXTOS, label: "Panel visible" }, OCULTO];
  return sidebarStore.shown === "mini" ? [CON_TEXTOS] : [CON_TEXTOS, SOLO_ICONOS, OCULTO];
});

function elegir(modo: SidebarMode, event: MouseEvent) {
  // `setMode` con el modo actual volvería al anterior: la vista actual no hace nada.
  if (modo === sidebarStore.mode) return;
  if (modo === "close") return sidebarStore.dismiss();
  sidebarStore.setMode(modo);
  // Con teclado (`detail` 0), el foco pasa a la única vista que queda en `mini`.
  if (modo === "mini" && event.detail === 0) panel.value?.querySelector<HTMLElement>(".sb-modes__opt--open")?.focus();
}

// Ancho, modo y drawer: todo el movimiento lo lleva GSAP (ver `sidebarMotion.ts`).
useSidebarMotion(sidebarStore, panel);
</script>
