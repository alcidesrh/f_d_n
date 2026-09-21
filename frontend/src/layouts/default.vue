<template>
  <div
    class="body-row"
    :data-sidebar-l="ui.leftState"
    :data-sidebar-r="ui.rightState"
  >
    <AppHeader>
      <template #menu-content></template>
    </AppHeader>
    <SidebarLeft />

    <main class="main">
      <div class="main-inner">
        <slot />
      </div>
    </main>
    <SidebarRight />
  </div>
</template>
<script setup lang="ts">
const route = useRoute();

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
