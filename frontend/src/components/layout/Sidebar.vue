<template>
  <aside class="sidebar" :class="[sidebarStore.side, sidebarStore.mode]">
    <nav>
      <div :class="[sidebarStore.side]" class="sidebar-control">
        <div @click="sidebarStore.setMode('close')">
          <icon name="x" />
        </div>
        <div @click="sidebarStore.setMode(sidebarStore.mode == 'mini' ? 'open' : 'mini')">
          <icon :name="sidebarStore.mode != 'mini' ? 'chevrons-left' : 'chevrons-right'" />
        </div>
      </div>
      <slot name="menu-content"> </slot>
    </nav>
  </aside>
</template>
<script setup lang="ts">
import type { StoreDefinition } from "pinia";

const props = defineProps<{ side?: "left" | "right"; store?: StoreDefinition }>();

const sidebarStore = props.store || defineSidebarStore(props.side)();

watch(
  () => sidebarStore.mode,
  () => sidebarStore.sidebarUpdate(),
);
// gsap.registerPlugin(CustomBounce, CustomEase);

onMounted(() => {
  sidebarStore.sidebarUpdate();
});
</script>
