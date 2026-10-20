<!--
  Lo que pasa hoy: salidas por estado, ocupación vendida del día y las
  próximas (de 30 min atrás a 4 h adelante) con lo vendido. Una salida que
  ya debió salir y sigue programada se marca: hay que abordarla o anularla.
-->
<template>
  <div class="flex flex-col gap-3">
    <div class="estados">
      <div v-for="e in ESTADOS" :key="e.id" class="estado">
        <icon :name="e.icono" class="text-muted-color" />
        <span class="tabular-nums text-lg font-semibold">{{ operacion.estados[e.id] ?? 0 }}</span>
        <span class="text-xs text-muted-color">{{ e.nombre }}</span>
      </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
      <span class="text-muted-color">{{ numero(operacion.asientosVendidos) }} asientos vendidos para hoy</span>
      <BarraOcupacion :valor="operacion.ocupacion" class="w-40" titulo="Ocupación vendida de las salidas de hoy" />
    </div>

    <div class="flex flex-col">
      <div class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-color">Próximas 4 horas</div>
      <p v-if="!operacion.proximas.length" class="py-3 text-sm text-muted-color">No hay salidas en las próximas 4 horas.</p>
      <RouterLink v-for="s in operacion.proximas" :key="s.id" :to="{ path: '/salidas' }" class="proxima">
        <div class="flex flex-col">
          <span class="tabular-nums font-semibold">{{ hora(s.fecha) }}</span>
          <span class="text-xs" :class="atrasada(s) ? 'font-semibold text-red-700' : 'text-muted-color'">{{ atrasada(s) ? 'sin abordar' : cuentaRegresiva(s.fecha, ahora) }}</span>
        </div>
        <div class="min-w-0">
          <div class="truncate text-sm">{{ s.ruta }}</div>
          <div class="truncate text-xs text-muted-color">Bus {{ s.bus ?? '—' }}<template v-if="s.empresa"> · {{ s.empresa }}</template> · {{ ETIQUETA[s.estado] }}</div>
        </div>
        <div class="flex flex-col items-end gap-0.5">
          <BarraOcupacion :valor="s.ocupacion" class="w-28" />
          <span class="tabular-nums text-xs text-muted-color">{{ s.boletos }}/{{ s.asientos }}</span>
        </div>
      </RouterLink>
      <p v-if="operacion.masProximas" class="pt-2 text-xs text-muted-color">y {{ operacion.masProximas }} más en el listado de salidas.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { cuentaRegresiva, fechaLocal, hora, numero } from '@/core/tablero/formato'
import type { EstadoSalida, OperacionHoy, SalidaProxima } from '@/core/tablero/types'
import BarraOcupacion from './BarraOcupacion.vue'

const props = defineProps<{ operacion: OperacionHoy; ahora: Date }>()

const ESTADOS: { id: EstadoSalida; nombre: string; icono: string }[] = [
  { id: 'programada', nombre: 'Programadas', icono: 'schedule-outline' },
  { id: 'abordando', nombre: 'Abordando', icono: 'login' },
  { id: 'iniciada', nombre: 'En ruta', icono: 'directions-bus-outline' },
  { id: 'finalizada', nombre: 'Finalizadas', icono: 'flag-outline' },
  { id: 'cancelada', nombre: 'Anuladas', icono: 'block' },
]
const ETIQUETA: Record<EstadoSalida, string> = { programada: 'programada', abordando: 'abordando', iniciada: 'en ruta', finalizada: 'finalizada', cancelada: 'anulada' }

const atrasada = (s: SalidaProxima) => s.estado === 'programada' && fechaLocal(s.fecha).getTime() < props.ahora.getTime()
</script>

<style scoped>
.estados {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
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
.proxima {
  display: grid;
  grid-template-columns: 4.5rem minmax(0, 1fr) auto;
  gap: 0.75rem;
  align-items: center;
  padding: 0.5rem 0;
  border-top: 1px solid var(--p-content-border-color);
  color: inherit;
  text-decoration: none;
}
.proxima:hover {
  background: var(--p-content-hover-background, rgba(127, 127, 127, 0.06));
}
</style>
