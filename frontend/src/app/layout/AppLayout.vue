<template>
  <BlankLayout v-if="route.meta.layout === 'blank'"><slot /></BlankLayout>
  <AppShell v-else><slot /></AppShell>
</template>

<script setup lang="ts">
import AppShell from './AppShell.vue'
import BlankLayout from './BlankLayout.vue'

const route = useRoute()
</script>
