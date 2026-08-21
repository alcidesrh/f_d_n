<template>
  <div class="shell">
    <AppHeader :crumbs="crumbs" />

    <div class="body-row">
      <SidebarLeft />

      <main class="main bg-surface-100" @click="ui.closeMobileOverlays()">
        <div class="main-inner">
          <slot />
        </div>
      </main>

      <SidebarRight :w="400" nomini>
        <template #default>  </template>
      </SidebarRight>

      <div class="backdrop" :class="{ show: showBackdrop }" @click="ui.closeMobileOverlays()"></div>
    </div>
    <DynamicDialog />
    <Toasts />
  </div>
</template>
<script setup lang="ts">
import SidebarLeft from '@/components/layout/SidebarLeft.vue'
import SidebarRight from '@/components/layout/SidebarRight.vue'
const route = useRoute()

const crumbs = computed(() => route.meta.crumbs ?? ['Andén'])
const showBackdrop = computed(() => ui.isMobile && (ui.mobileLeftOpen || ui.mobileRightOpen))

function handleResize() {
  ui.syncViewport()
}

onMounted(() => window.addEventListener('resize', handleResize))
onBeforeUnmount(() => window.removeEventListener('resize', handleResize))

watchEffect(() => {
  const el = document.documentElement
  el.className = ui.mode
  el.classList.add(`primary-${ui.primary}`)
  el.classList.add(`surface-${ui.surface}`)
})
</script>
