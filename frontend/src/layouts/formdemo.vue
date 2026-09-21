<template>
  <div class="shell">
    <AppHeader />

    <div class="body-row">
      <SidebarLeft />

      <main class="main" @click="ui.closeMobileOverlays()">
        <div class="main-inner">
          <slot />
        </div>
      </main>

      <Sidebar :store="sidebarStore" :nomini="true">
        <template #menu-content>
          <FormBuilderPanel />
        </template>
      </Sidebar>
    </div>
  </div>
</template>
<script setup lang="ts">
import SidebarLeft from "@/components/layout/SidebarLeft.vue";
const sidebarStore = defineSidebarStore("right", "formBuild")();
sidebarStore.open = 350;

function handleResize() {
  ui.syncViewport();
}

onMounted(() => window.addEventListener("resize", handleResize));
onBeforeUnmount(() => window.removeEventListener("resize", handleResize));

watchEffect(() => {
  const el = document.documentElement;
  el.className = ui.mode;
  el.classList.add(`primary-${ui.primary}`);
  el.classList.add(`surface-${ui.surface}`);
});
</script>
