<template>
  <AppLayout>
    <router-view v-slot="{ Component, route }">
      <transition name="fade" mode="out-in">
        <component :is="Component" :key="route.fullPath" />
      </transition>
    </router-view>
  </AppLayout>
</template>
<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
</script>
