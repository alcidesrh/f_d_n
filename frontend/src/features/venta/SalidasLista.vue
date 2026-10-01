<!-- Salidas del día que pasan por la estación; clic = abrir su croquis. -->
<template>
  <DataTable
    :value="salidas"
    :loading="cargando"
    data-key="id"
    selection-mode="single"
    :selection="seleccionado"
    scrollable
    scroll-height="18rem"
    size="small"
    striped-rows
    :row-class="(r: SalidaResumen) => (vendible(r) ? '' : 'opacity-60')"
    @row-select="emit('elegir', $event.data.id)"
  >
    <template #empty>
      <div class="p-3 text-center text-muted-color">No hay salidas para esa fecha y estación.</div>
    </template>
    <Column header="Salida" class="whitespace-nowrap">
      <template #body="{ data }">
        <div class="font-medium">{{ hora(data.salidaEstacion ?? data.salida) }}</div>
        <div
          v-if="data.salidaEstacion && data.salidaEstacion !== data.salida"
          class="text-xs text-muted-color"
        >
          sale {{ hora(data.salida) }} de {{ data.trayecto.origen.nombre }}
        </div>
      </template>
    </Column>
    <Column header="Origen" field="trayecto.origen.nombre" style="min-width: 8rem" />
    <Column header="Destino" field="trayecto.destino.nombre" style="min-width: 8rem" />
    <Column header="Empresa" field="empresa.nombre" style="min-width: 8rem" />
    <Column header="Bus" style="min-width: 7rem">
      <template #body="{ data }">
        <template v-if="data.bus"
          >{{ data.bus.codigo
          }}<span v-if="data.bus.gama" class="text-xs text-muted-color">
            · {{ data.bus.gama }}</span
          ></template
        >
        <Chip v-else label="Sin bus" class="chip-warn" />
      </template>
    </Column>
    <Column header="Ocupación" class="whitespace-nowrap">
      <template #body="{ data }">
        <span v-if="data.capacidad">{{ data.vendidos ?? 0 }}/{{ data.capacidad }}</span>
      </template>
    </Column>
    <Column header="Estado">
      <template #body="{ data }">
        <Chip :label="data.estado" :class="{ 'chip-success': data.estado === 'programada' }" />
      </template>
    </Column>
  </DataTable>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { hora } from '@/core/venta/modelo'
import type { SalidaResumen } from '@/core/venta/types'

const props = defineProps<{
  salidas: SalidaResumen[]
  cargando: boolean
  salidaId: number | null
}>()
const emit = defineEmits<{ elegir: [id: number] }>()

const seleccionado = computed(
  () => props.salidas.find((r) => r.id === props.salidaId) ?? null,
)
const vendible = (r: SalidaResumen) => ['programada', 'abordando'].includes(r.estado) && !!r.bus
</script>

<style scoped>
.chip-warn {
  background: var(--p-orange-100);
  color: var(--p-orange-800);
}
.chip-success {
  background: var(--p-green-100);
  color: var(--p-green-800);
}
:global(.darks) .chip-warn {
  background: color-mix(in srgb, var(--p-orange-400) 20%, transparent);
  color: var(--p-orange-300);
}
:global(.darks) .chip-success {
  background: color-mix(in srgb, var(--p-green-400) 20%, transparent);
  color: var(--p-green-300);
}
</style>
