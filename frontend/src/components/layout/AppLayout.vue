<template>
  <component :is="layoutComponent">
    <slot />
  </component>
</template>

<script setup lang="ts">
import DefaultLayout from '@/layouts/default.vue'
import AuthLayout from '@/layouts/auth.vue'
import BlankLayout from '@/layouts/blank.vue'
import FormDemoLayout from '@/layouts/formdemo.vue'
import type { Component } from 'vue'

const route = useRoute()

const layouts: Record<string, Component> = {
  default: DefaultLayout,
  auth: AuthLayout,
  blank: BlankLayout,
  formdemo: FormDemoLayout,
}

const layoutComponent = computed(() => {
  const layoutName = (route.meta.layout as string) || 'default'
  return layouts[layoutName] || DefaultLayout
})
</script>
