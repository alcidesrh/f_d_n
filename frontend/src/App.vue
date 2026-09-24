<template>
  <TopLoadingBar />
  <transition v-if="mounted" name="fade" mode="out-in">
    <AppLayout :key="route.meta.layout ?? 'app'">
      <router-view v-slot="{ Component, route: r }">
        <transition name="fade" mode="out-in">
          <component :is="Component" :key="r.fullPath" />
        </transition>
      </router-view>
    </AppLayout>
  </transition>
  <ConfirmDialog />
  <DynamicDialog />
  <Toasts />
</template>

<script setup lang="ts">
import AppLayout from '@/app/layout/AppLayout.vue'
import Toasts from '@/app/layout/Toasts.vue'
import TopLoadingBar from '@/app/layout/TopLoadingBar.vue'
import { useUiStore } from '@/app/ui'

const route = useRoute()
const ui = useUiStore()
const mounted = ref(false)

const syncViewport = () => ui.syncViewport()
onMounted(() => {
  mounted.value = true
  window.addEventListener('resize', syncViewport)
})
onBeforeUnmount(() => window.removeEventListener('resize', syncViewport))

/** Clases de tema en `<html>` (modo, color primario y superficie). */
watchEffect(() => {
  document.documentElement.className = `${ui.mode} primary-${ui.primary} surface-${ui.surface}`
})
</script>
