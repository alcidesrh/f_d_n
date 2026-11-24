<!-- Una fila de la lista de la dona: puesto (si es un ranking), nombre, valor, parte del total y barra contra la mayor. -->
<template>
  <div class="fila" :class="{ activa }" :title="fila.nombre !== fila.etiqueta ? fila.nombre : undefined">
    <span class="muestra" :style="{ background: color }" />
    <span class="min-w-0">
      <span class="flex items-baseline justify-between gap-2">
        <span class="truncate font-medium"><span v-if="puesto !== null" class="puesto">{{ puesto }}.</span>{{ fila.etiqueta }}</span>
        <span class="whitespace-nowrap font-semibold tabular-nums">{{ valor }}</span>
      </span>
      <span class="pista"><span class="relleno" :style="{ width: `${Math.max(0, Math.min(1, barra)) * 100}%`, background: color }" /></span>
      <span class="flex items-baseline justify-between gap-2 text-xs text-muted-color">
        <span class="truncate">{{ secundario }}</span>
        <span class="whitespace-nowrap tabular-nums">{{ parte }}</span>
      </span>
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Porcion } from '@/core/tablero/porciones'

const props = defineProps<{ fila: Porcion; puesto: number | null; color: string; valor: string; parte: string; barra: number; activa: boolean }>()

/** El nombre completo (si no es la etiqueta) y el detalle. */
const secundario = computed(() => [props.fila.nombre !== props.fila.etiqueta ? props.fila.nombre : null, props.fila.detalle].filter(Boolean).join(' · '))
</script>

<style scoped>
.fila {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.5rem;
  align-items: start;
  padding: 0.375rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}
.fila.activa {
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.08));
}
.muestra {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 2px;
  margin-top: 0.3rem;
}
.puesto {
  margin-right: 0.375rem;
  color: var(--p-text-muted-color);
  font-variant-numeric: tabular-nums;
  font-weight: 400;
}
.pista {
  display: block;
  height: 0.25rem;
  margin: 0.25rem 0;
}
.relleno {
  display: block;
  height: 100%;
  min-width: 2px;
  border-radius: 0 2px 2px 0;
}
</style>
