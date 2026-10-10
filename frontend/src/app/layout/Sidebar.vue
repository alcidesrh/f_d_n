<template>
  <aside ref="panel" class="sidebar" :class="[sidebarStore.side, sidebarStore.mode, { 'drawer-open': sidebarStore.drawer }]">
    <div :class="[sidebarStore.side, nomini ? 'nomini' : '']" class="sidebar-control">
      <button type="button" class="toggle-sidebar tap-target" :aria-label="sidebarStore.mode != 'mini' ? 'Solo íconos' : 'Mostrar textos'" @click="sidebarStore.setMode(sidebarStore.mode == 'mini' ? 'open' : 'mini')">
        <icon :name="sidebarStore.mode != 'mini' ? 'horizontal_align_left' : 'horizontal_align_right'" size="1.2rem" class="text-surface-500 font-bold" :class="{ 'rotate-180': sidebarStore.side != 'left' }" :weight="400" />
      </button>
      <button type="button" class="close-sidebar tap-target" aria-label="Cerrar panel" @click="sidebarStore.dismiss()">
        <icon name="close" size="1.2rem" class="text-surface-500" :weight="400" />
      </button>
    </div>
    <nav class="mt-2">
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
