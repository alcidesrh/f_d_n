<!-- Salidas del día que pasan por la estación; clic = abrir su croquis. -->
<template>
  <DataTable :value="salidas" :loading="cargando" data-key="id" selection-mode="single" :selection="seleccionado" scrollable scroll-height="38rem" size="small" :row-class="(r: SalidaResumen) => (vendible(r) ? '' : r.estado)" @row-select="emit('elegir', $event.data.id)">
    <template #empty>
      <div class="p-3 text-center text-muted-color">No hay salidas para esa fecha y estación.</div>
    </template>
    <Column header="Salida" class="whitespace-nowrap">
      <template #body="{ data }">
        <div class="font-medium">{{ hora(data.salidaEstacion ?? data.salida) }}</div>
        <div v-if="data.salidaEstacion && data.salidaEstacion !== data.salida" class="text-xs text-muted-color">sale {{ hora(data.salida) }} de {{ data.trayecto.origen.nombre }}</div>
      </template>
    </Column>
    <Column header="Origen" field="trayecto.origen.nombre" style="min-width: 8rem" />
    <Column header="Destino" field="trayecto.destino.nombre" style="min-width: 8rem" />
    <Column header="Empresa" field="empresa.nombre" style="min-width: 8rem" />
    <Column header="Bus" style="min-width: 7rem">
      <template #body="{ data }">
        <template v-if="data.bus"
          >{{ data.bus.codigo }}<span v-if="data.bus.gama" class="text-xs text-muted-color"> · {{ data.bus.gama }}</span></template
        >
        <Chip v-else label="Sin bus" class="chip-warn" />
      </template>
    </Column>
    <Column header="Estado">
      <template #body="{ data }">
        <div class="text-sm" v-text="data.estado" :class="[`chip-${data.estado}`]" />
      </template>
    </Column>
    <Column class="whitespace-nowrap">
      <template #header>
        <icon name="airline_seat_recline_extra" classs="text-red-400" />
        <span class="text-surface-400">|</span>
        <icon name="tatami_seat" class="text-surface-500" size="1.1rem" :weight="300" />
      </template>
      <template #body="{ data }">
        <div v-if="data.capacidad" class="m-auto">
          <span>{{ data.vendidos ?? 0 }}</span>
          <span class="mx-[9.5px] text-surface-400">|</span>
          <span>{{ data.capacidad - data.vendidos }}</span>
        </div>
      </template>
    </Column>
  </DataTable>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { hora } from "@/core/venta/modelo";
import type { SalidaResumen } from "@/core/venta/types";

const props = defineProps<{
  salidas: SalidaResumen[];
  cargando: boolean;
  salidaId: number | null;
}>();
const emit = defineEmits<{ elegir: [id: number] }>();

const seleccionado = computed(() => props.salidas.find((r) => r.id === props.salidaId) ?? null);
const vendible = (r: SalidaResumen) => ["programada", "abordando"].includes(r.estado) && !!r.bus;
</script>

<style scoped>
:deep(tr.anulada),
:deep(tr.cancelada) {
  position: relative;
  &::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    border-top: 1px solid var(--p-red-400);
    pointer-events: none;
  }
}
.chip-programada,
.chip-cancelada,
.chip-abordando,
.chip-iniciada,
.chip-warn {
  font-weight: 800;
  width: fit-content;
  font-size: 9.5px;
  padding: 4px 8px !important;
  border-radius: 5px;
  text-transform: uppercase;
}
.chip-cancelada,
.chip-warn,
.chip-anulada {
  background: var(--p-orange-50);
  color: var(--p-orange-800);
}
.chip-iniciada {
  background: var(--p-green-50);
  color: var(--p-green-800);
}
.chip-programada {
  background: var(--p-blue-50);
  color: var(--p-blue-800);
}
.chip-abordando {
  background: var(--p-green-50);
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
