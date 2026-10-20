<!-- Mezcla de canales: participación en los ingresos, ticket promedio y variación de cada canal. -->
<template>
  <div class="flex flex-col gap-3">
    <div class="barra-total" role="img" :aria-label="canales.map((c) => `${nombre(c.canal)} ${porciento(parte(c))}`).join(', ')">
      <span v-for="c in conVenta" :key="c.canal" :style="{ flexGrow: c.ingresos, background: `var(--serie-${c.canal})` }" />
    </div>
    <ul class="flex flex-col gap-2.5">
      <li v-for="c in canales" :key="c.canal" class="canal">
        <span class="muestra" :style="{ background: `var(--serie-${c.canal})` }" />
        <div class="min-w-0">
          <div class="flex items-baseline justify-between gap-2">
            <span class="font-medium">{{ nombre(c.canal) }}</span>
            <span class="tabular-nums font-semibold">{{ dinero(c.ingresos, true) }}</span>
          </div>
          <div class="flex flex-wrap items-baseline justify-between gap-x-2 text-xs text-muted-color">
            <span>{{ porciento(parte(c)) }} · {{ numero(c.boletos) }} boletos · ticket {{ dinero(c.boletos ? Math.round(c.ingresos / c.boletos) : null, true) }}</span>
            <span v-if="variacion(c.ingresos, c.anterior)" :class="clase(variacion(c.ingresos, c.anterior)!.valoracion)">
              {{ variacion(c.ingresos, c.anterior)!.texto }}
            </span>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { dinero, numero, porciento, variacion } from '@/core/tablero/formato'
import type { Canal, CanalVentas } from '@/core/tablero/types'

const props = defineProps<{ canales: CanalVentas[] }>()

const NOMBRES: Record<Canal, string> = { estacion: 'Taquilla', agencia: 'Agencias', web: 'Página web' }
const nombre = (c: Canal) => NOMBRES[c]
const total = computed(() => props.canales.reduce((s, c) => s + c.ingresos, 0))
const conVenta = computed(() => props.canales.filter((c) => c.ingresos > 0))
const parte = (c: CanalVentas) => (total.value ? c.ingresos / total.value : 0)
const clase = (v: string) => (v === 'buena' ? 'text-green-700 font-semibold' : v === 'mala' ? 'text-red-700 font-semibold' : '')
</script>

<style scoped>
.barra-total {
  display: flex;
  gap: 2px;
  height: 0.75rem;
  border-radius: 4px;
  overflow: hidden;
  background: var(--p-content-border-color);
}
.barra-total span {
  flex-basis: 0;
  min-width: 2px;
}
.canal {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.5rem;
  align-items: start;
  font-size: 0.875rem;
}
.muestra {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 2px;
  margin-top: 0.3rem;
}
</style>
