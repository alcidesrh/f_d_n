<template>
  <div class="shell">
    <AppHeader :right-sidebar="rightSidebar" />
    <div class="body-row">
      <SidebarLeft />
      <main class="main">
        <div class="main-inner">
          <slot />
        </div>
      </main>
      <Sidebar v-if="panel" :key="String(route.name)" :store="rightSidebar" nomini>
        <template #menu-content>
          <component :is="panel" />
        </template>
      </Sidebar>
      <SidebarRight v-else />
    </div>
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from "vue";
import AppHeader from "./AppHeader.vue";
import Sidebar from "./Sidebar.vue";
import SidebarLeft from "./SidebarLeft.vue";
import SidebarRight from "./SidebarRight.vue";
import { defineSidebarStore } from "./sidebarStore";

const route = useRoute();

/** Panel propio de la ruta (`meta.panel`), con su propio store de ancho/modo. */
const panel = computed(() => (route.meta.panel ? defineAsyncComponent(route.meta.panel.component) : null));

const rightSidebar = computed(() => {
  if (!route.meta.panel) return defineSidebarStore("right")();
  const store = defineSidebarStore("right", `panel:${String(route.name)}`)();
  store.open = route.meta.panel.width ?? store.open;
  return store;
});
</script>
