<template>
  <div
    class="min-h-screen w-full bg-surface-50 dark:bg-surface-950 text-surface-900 dark:text-surface-50 transition-colors duration-300"
  >
    <slot />
    <DynamicDialog />
    <Toasts />
  </div>
</template>

<script setup lang="ts">
watchEffect(() => {
  const el = document.documentElement
  el.className = ui.mode
  el.classList.add(`primary-${ui.primary}`)
  el.classList.add(`surface-${ui.surface}`)
})
</script>
