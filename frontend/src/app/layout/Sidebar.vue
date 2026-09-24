<template>
  <aside class="sidebar" :class="[sidebarStore.side, sidebarStore.mode]">
    <nav>
      <div :class="[sidebarStore.side, nomini ? 'nomini' : '']" class="sidebar-control">
        <div class="close-sidebar" @click="sidebarStore.setMode('close')">
          <icon name="x" />
        </div>
        <div
          class="toggle-sidebar"
          @click="sidebarStore.setMode(sidebarStore.mode == 'mini' ? 'open' : 'mini')"
        >
          <icon :name="sidebarStore.mode != 'mini' ? 'chevrons-left' : 'chevrons-right'" />
        </div>
      </div>
      <slot name="menu-content"> </slot>
    </nav>
  </aside>
</template>
<script setup lang="ts">
import { defineSidebarStore, type SidebarStore } from './sidebarStore'

const props = defineProps<{ side?: 'left' | 'right'; store?: SidebarStore; nomini?: boolean }>()

const sidebarStore = props.store ?? defineSidebarStore(props.side ?? 'left')()

watch(
  () => sidebarStore.mode,
  () => sidebarStore.sidebarUpdate(),
)
onMounted(() => sidebarStore.sidebarUpdate())
</script>
