<template>
  <div v-if="sidebarStore.side == 'left'" class="sidebar-control">
    <div class="p-[0px]" @click="sidebarStore.setMode('close')">
      <icon name="x" />
    </div>
    <div
      class="p-[0px]"
      @click="sidebarStore.setMode(sidebarStore.mode == 'mini' ? 'open' : 'mini')"
    >
      <icon :name="sidebarStore.mode != 'mini' ? 'chevrons-left' : 'chevrons-right'" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  sidebarStore: import("pinia").Store<
    string,
    import("pinia").StateTree,
    import("pinia")._GettersTree<import("pinia").StateTree>,
    import("pinia")._ActionsTree
  >;
}>();
</script>
