<template>
  <div class="body-row">
    <AppHeader :crumbs="crumbs">
      <template #menu-content></template>
    </AppHeader>
    <SidebarLeft>
      <template #menu-content></template>
    </SidebarLeft>

    <main class="main" @click="ui.closeMobileOverlays()">
      <div class="main-inner">
        <slot />
      </div>
    </main>

    <SidebarRight>
      <template #menu-content></template>
    </SidebarRight>

    <!-- <div class="body-row">
      <div class="backdrop" :class="{ show: showBackdrop }" @click="ui.closeMobileOverlays()"></div>
    </div> -->
    <DynamicDialog />
    <Toasts />
  </div>
</template>
<script setup lang="ts">
const route = useRoute();

const crumbs = computed(() => route.meta.crumbs ?? ["Andén"]);
const showBackdrop = computed(() => ui.isMobile && (ui.mobileLeftOpen || ui.mobileRightOpen));

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
