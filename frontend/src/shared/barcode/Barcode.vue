<!-- Código de barras Code 128 (boletos). -->
<template>
  <figure class="inline-flex flex-col items-center gap-1" :aria-label="`Código ${valor}`">
    <svg
      :viewBox="`0 0 ${datos.modulos} ${alto}`"
      :height="alto"
      class="max-w-full"
      shape-rendering="crispEdges"
      role="img"
    >
      <rect :width="datos.modulos" :height="alto" fill="#fff" />
      <rect
        v-for="[x, w] in datos.barras"
        :key="x"
        :x="x"
        y="0"
        :width="w"
        :height="alto"
        fill="#000"
      />
    </svg>
    <figcaption v-if="conTexto" class="font-mono tracking-widest">{{ valor }}</figcaption>
  </figure>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { barras } from './code128'

const props = withDefaults(defineProps<{ valor: string; alto?: number; conTexto?: boolean }>(), {
  alto: 50,
  conTexto: true,
})

const datos = computed(() => barras(props.valor))
</script>
