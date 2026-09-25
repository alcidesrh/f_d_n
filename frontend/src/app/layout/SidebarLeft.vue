<template>
  <Sidebar side="left">
    <template #menu-content>
      <NavArea area="sidebar_left" :sidebar="sidebarStore" />
    </template>
  </Sidebar>
</template>
<script setup lang="ts">
import NavArea from './navigation/NavArea.vue'
import Sidebar from './Sidebar.vue'
import { defineSidebarStore } from './sidebarStore'

const sidebarStore = defineSidebarStore('left')()
</script>
