<script setup lang="ts">
import { computed } from 'vue'
import { ICON_PATHS } from './icon-paths'

const props = withDefaults(
  defineProps<{
    name: string
    size?: number | string
    filled?: boolean
  }>(),
  { size: 18, filled: false }
)

const markup = computed(() => ICON_PATHS[props.name] ?? '')
</script>

<template>
  <!--
    `markup` only ever comes from the static ICON_PATHS map above, never from
    user input, so `v-html` here is safe. ESLint's vue/no-v-html rule still
    flags it as a warning by design (not an error) — intentionally left
    visible rather than suppressed.
  -->
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    :fill="filled ? 'currentColor' : 'none'"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    v-html="markup"
  />
</template>
