<template>
  <aside ref="panel" class="sidebar" :class="[sidebarStore.side, sidebarStore.shown]">
    <div :class="[sidebarStore.side, nomini ? 'nomini' : '']" class="sidebar-control">
      <!-- Modo del panel (solo escritorio): con textos o solo íconos; la pastilla se desliza a la opción elegida. -->
      <div v-if="!nomini" class="sb-modes" :class="{ 'sb-modes--mini': sidebarStore.mode === 'mini' }" role="radiogroup" aria-label="Modo del panel">
        <span class="sb-modes__thumb" aria-hidden="true" />
        <button type="button" role="radio" class="sb-modes__opt" :aria-checked="sidebarStore.mode !== 'mini'" aria-label="Con textos" v-tooltip.bottom="'Con textos'" @click="sidebarStore.setMode('open')">
          <icon name="side-navigation" size="1.05rem" color="text-inherit" :class="{ '-scale-x-100': sidebarStore.side != 'left' }" />
        </button>
        <button type="button" role="radio" class="sb-modes__opt" :aria-checked="sidebarStore.mode === 'mini'" aria-label="Solo íconos" v-tooltip.bottom="'Solo íconos'" @click="sidebarStore.setMode('mini')">
          <icon name="dock-to-left" size="1.05rem" color="text-inherit" :class="{ '-scale-x-100': sidebarStore.side != 'left' }" />
        </button>
      </div>
      <button type="button" class="close-sidebar tap-target" aria-label="Cerrar panel" v-tooltip.bottom="'Cerrar panel'" @click="sidebarStore.dismiss()">
        <icon name="left-panel-close" size="1.15rem" color="text-inherit" :class="{ '-scale-x-100': sidebarStore.side != 'left' }" />
      </button>
    </div>
    <nav class="mt-3">
      <slot name="menu-content"> </slot>
    </nav>
  </aside>
</template>
<script setup lang="ts">
import { useTemplateRef } from "vue";
import { defineSidebarStore, type SidebarStore } from "./sidebarStore";
import { useSidebarMotion } from "./sidebarMotion";

const props = defineProps<{ side?: "left" | "right"; store?: SidebarStore; nomini?: boolean }>();

const sidebarStore = props.store ?? defineSidebarStore(props.side ?? "left")();
const panel = useTemplateRef<HTMLElement>("panel");

// Ancho, modo y drawer: todo el movimiento lo lleva GSAP (ver `sidebarMotion.ts`).
useSidebarMotion(sidebarStore, panel);
</script>
