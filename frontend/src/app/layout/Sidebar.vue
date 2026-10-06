<template>
  <aside ref="panel" class="sidebar" :class="[sidebarStore.side, sidebarStore.mode, { 'drawer-open': sidebarStore.drawer }]">
    <div :class="[sidebarStore.side, nomini ? 'nomini' : '']" class="sidebar-control">
      <button type="button" class="toggle-sidebar tap-target" :aria-label="sidebarStore.mode != 'mini' ? 'Solo íconos' : 'Mostrar textos'" @click="sidebarStore.setMode(sidebarStore.mode == 'mini' ? 'open' : 'mini')">
        <!-- <icon :name="sidebarStore.mode != 'mini' ? 'keyboard-double-arrow-left' : 'keyboard-double-arrow-right'" size="sm" /> -->
        <icon :name="sidebarStore.mode != 'mini' ? 'chevron-left' : 'chevron-right'" size="1.5rem" class="text-surface-400 font-bold" :class="{ 'rotate-180': sidebarStore.side != 'left' }" :weight="400" />
      </button>
      <button type="button" class="close-sidebar tap-target" aria-label="Cerrar panel" @click="sidebarStore.dismiss()">
        <!-- <icon name="close" size="sm" /> -->
        <icon name="close" size="1.3rem" class="text-surface-400" />
      </button>
    </div>
    <nav>
      <slot name="menu-content"> </slot>
    </nav>
  </aside>
</template>
<script setup lang="ts">
import { nextTick, useTemplateRef } from "vue";
import { defineSidebarStore, type SidebarStore } from "./sidebarStore";

const props = defineProps<{ side?: "left" | "right"; store?: SidebarStore; nomini?: boolean }>();

const sidebarStore = props.store ?? defineSidebarStore(props.side ?? "left")();
const panel = useTemplateRef<HTMLElement>("panel");

watch(
  () => sidebarStore.mode,
  () => sidebarStore.sidebarUpdate(),
);
onMounted(() => sidebarStore.sidebarUpdate());

// Drawer (móvil): al abrirse, el foco entra al panel.
watch(
  () => sidebarStore.drawer,
  async (open) => {
    if (!open) return;
    await nextTick();
    panel.value?.querySelector<HTMLElement>(".close-sidebar")?.focus();
  },
);
</script>
