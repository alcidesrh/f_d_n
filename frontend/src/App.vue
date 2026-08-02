<template>
  <div class="shell">
    <AppHeader :crumbs="crumbs" />

    <div class="body-row">
      <SidebarLeft />

      <main class="main" @click="ui.closeMobileOverlays()">
        <div class="main-inner">
          <router-view v-slot="{ Component }">
            <transition name="fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </main>

      <SidebarRight />

      <div class="backdrop" :class="{ show: showBackdrop }" @click="ui.closeMobileOverlays()"></div>
    </div>
  </div>
</template>
<script setup lang="ts">
const ui = useUiStore();
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
  // el.classList.contains(`mode-${ui.mode}`);
  // el.classList.toggle(`mode-${ui.mode}`);
  // el.classList.toggle(`dark`);
});
</script>
