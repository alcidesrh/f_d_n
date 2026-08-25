<template>
  <transition name="fade" mode="out-in">
    <AppLayout :key="layoutKey">
      <router-view v-slot="{ Component, route: r }">
        <transition name="fade" mode="out-in">
          <component :is="Component" :key="r.fullPath" />
        </transition>
      </router-view>
    </AppLayout>
  </transition>
  <ConfirmDialog></ConfirmDialog>
  <Toast />
</template>
<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'

const route = useRoute()

const layoutKey = computed(() => (route.meta.layout as string) || 'default')
</script>
