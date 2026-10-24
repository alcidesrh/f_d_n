<!--
  Lo que pasa hoy: ocupación vendida del día y boletos de hoy (sin la página
  web) por estado, con los tres usuarios que más anularon, reasignaron y
  cancelaron en cada uno.
-->
<template>
  <div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span class="text-muted-color">{{ numero(operacion.asientosVendidos) }} asientos vendidos para hoy</span>
      <BarraOcupacion :valor="operacion.ocupacion" class="w-40" titulo="Ocupación vendida de las salidas de hoy" />
    </div>

    <div class="flex flex-col gap-2">
      <div class="text-xs font-medium uppercase tracking-wide text-muted-color">Boletos de hoy · sin página web</div>
      <div class="estados">
        <div v-for="e in ESTADOS_BOLETO" :key="e.id" class="estado">
          <icon :name="e.icono" class="text-muted-color" />
          <span class="tabular-nums text-lg font-semibold">{{ numero(operacion.boletos.estados[e.id]) }}</span>
          <span class="text-xs text-muted-color">{{ e.nombre }}</span>
        </div>
      </div>
    </div>

    <div v-for="e in RANKING" :key="e.id" class="flex flex-col">
      <div class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-color">Quién más {{ e.verbo }}</div>
      <p v-if="!operacion.boletos.porEstado[e.id].length" class="py-2 text-sm text-muted-color">Ninguno hoy.</p>
      <ol v-else class="flex flex-col">
        <li v-for="(u, i) in operacion.boletos.porEstado[e.id]" :key="u.id" class="fila">
          <span class="min-w-0 truncate text-sm"><span class="tabular-nums text-muted-color">{{ i + 1 }}.</span> {{ u.nombre }} <span class="text-xs text-muted-color">{{ u.usuario }}</span></span>
          <span class="tabular-nums font-semibold">{{ numero(u.boletos) }}</span>
        </li>
      </ol>
    </div>
  </div>
</template>

<script setup lang="ts">
import { numero } from '@/core/tablero/formato'
import type { EstadoBoleto, EstadoRanking, OperacionHoy } from '@/core/tablero/types'
import BarraOcupacion from './BarraOcupacion.vue'

defineProps<{ operacion: OperacionHoy }>()

const ESTADOS_BOLETO: { id: EstadoBoleto; nombre: string; icono: string }[] = [
  { id: 'emitido', nombre: 'Emitidos', icono: 'confirmation-number-outline' },
  { id: 'anulado', nombre: 'Anulados', icono: 'block' },
  { id: 'reasignado', nombre: 'Reasignados', icono: 'swap-horiz' },
  { id: 'cancelado', nombre: 'Cancelados', icono: 'person-off-outline' },
]
const RANKING: { id: EstadoRanking; verbo: string }[] = [
  { id: 'anulado', verbo: 'anuló' },
  { id: 'reasignado', verbo: 'reasignó' },
  { id: 'cancelado', verbo: 'canceló' },
]
</script>

<style scoped>
.estados {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(5.5rem, 1fr));
  gap: 0.25rem;
}
.estado {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.125rem;
  padding: 0.5rem 0.25rem;
  border-radius: 0.5rem;
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.06));
  min-width: 0;
  text-align: center;
}
.estado span:last-child {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}
.fila {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.375rem 0;
  border-top: 1px solid var(--p-content-border-color);
}
.fila:first-child {
  border-top: 0;
}
</style>
