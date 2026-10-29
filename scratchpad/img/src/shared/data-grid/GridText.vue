<template>
  <span class="grid-text" :title="text || undefined"><template v-for="(segment, index) in segments" :key="index"><mark v-if="segment.match" class="grid-text__match">{{ segment.text }}</mark><template v-else>{{ segment.text }}</template></template></span>
</template>

<script setup lang="ts">
/** Texto de una celda en una línea (con elipsis) y las coincidencias de `needle` resaltadas. */
import { computed } from 'vue'
import { splitMatches } from './highlight'

const props = defineProps<{ text: string; needle?: unknown }>()

const segments = computed(() => splitMatches(props.text, props.needle))
</script>

<style scoped>
.grid-text {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.grid-text__match {
  border-radius: 0.2rem;
  background: color-mix(in srgb, var(--c-warning) 35%, transparent);
  color: inherit;
  padding-inline: 0.05rem;
}
</style>
