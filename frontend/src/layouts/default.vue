<template>
  <div class="body-row" :data-sidebar-l="ui.leftState" :data-sidebar-r="ui.rightState">
    <AppHeader :crumbs="crumbs">
      <template #menu-content></template>
    </AppHeader>
    <SidebarLeft side="left">
      <template #menu-content></template>
    </SidebarLeft>

    <main class="main" @click="ui.closeMobileOverlays()">
      <div class="main-inner">
        <slot />
      </div>
    </main>

    <SidebarLeft side="right">
      <template #menu-content></template>
    </SidebarLeft>
  </div>
</template>
<script setup lang="ts">
const route = useRoute();
const crumbs = computed(() => route.meta.crumbs ?? ["Andén"]);

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
