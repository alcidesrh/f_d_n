<!-- Los puntos de venta que más ingresan en el periodo (taquillas, agencias, página), con su variación. El color es el del canal. -->
<template>
  <div class="flex flex-col gap-2.5">
    <p v-if="!puntos.length" class="py-4 text-center text-sm text-muted-color">Sin ventas en el periodo.</p>
    <div v-for="p in puntos" :key="`${p.tipo}-${p.nombre}`" class="punto">
      <div class="flex items-baseline justify-between gap-2 text-sm">
        <span class="truncate" :title="p.nombre">{{ p.nombre }}</span>
        <span class="whitespace-nowrap tabular-nums font-semibold">{{ dinero(p.ingresos, true) }}</span>
      </div>
      <div class="pista"><div class="relleno" :style="{ width: `${(p.ingresos / maximo) * 100}%`, background: `var(--serie-${p.tipo})` }" /></div>
      <div class="flex justify-between gap-2 text-xs text-muted-color">
        <span>{{ TIPOS[p.tipo] }} · {{ numero(p.boletos) }} boletos</span>
        <span v-if="variacion(p.ingresos, p.anterior)" :class="variacion(p.ingresos, p.anterior)!.valoracion === 'mala' ? 'text-red-700' : variacion(p.ingresos, p.anterior)!.valoracion === 'buena' ? 'text-green-700' : ''">
          {{ variacion(p.ingresos, p.anterior)!.texto }}
        </span>
        <span v-else-if="!p.anterior">nuevo</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { dinero, numero, variacion } from '@/core/tablero/formato'
import type { Canal, PuntoVenta } from '@/core/tablero/types'

const props = defineProps<{ puntos: PuntoVenta[] }>()
const TIPOS: Record<Canal, string> = { estacion: 'Taquilla', agencia: 'Agencia', web: 'Página web' }
const maximo = computed(() => Math.max(1, ...props.puntos.map((p) => p.ingresos)))
</script>

<style scoped>
.punto {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.pista {
  height: 0.375rem;
  border-radius: 0 4px 4px 0;
  overflow: hidden;
}
.relleno {
  height: 100%;
  border-radius: 0 4px 4px 0;
  min-width: 2px;
}
</style>
