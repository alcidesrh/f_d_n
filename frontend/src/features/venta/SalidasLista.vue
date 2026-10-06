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
        <icon v-else name="no_transfer" class="text-surface-500" size="1.1rem" />
        <!-- <Chip v-else label="Sin bus" class="chip-warn" /> -->
      </template>
    </Column>
    <Column header="Estado">
      <template #body="{ data }">
        <div class="text-sm tracking-wider estado" v-text="data.estado" />
      </template>
    </Column>
    <Column class="whitespace-nowrap">
      <template #header>
        <div class="flex">
          <div class="w-[20px] text-center">
            <icon name="airline_seat_recline_extra" class="text-emerald-600" />
          </div>
          <div class="w-[20px] mx-[5px] text-center">
            <icon name="tatami_seat" class="text-neutral-500" size="1rem" :weight="300" />
          </div>
          <div class="w-[20px] text-center">
            <icon name="tatami_seat" size="1rem" :weight="300" class="text-surface-800" />
            <icon name="airline_seat_recline_extra" class="text-surface-800" />
          </div>
        </div>
      </template>
      <template #body="{ data }">
        <div v-if="data.capacidad" class="m-auto flex text-xs font-bold">
          <div class="w-[20px] text-center text-emerald-600">{{ data.vendidos ?? 0 }}</div>
          <div class="w-[20px] text-neutral-600 mx-[5px] text-center">{{ data.capacidad - data.vendidos }}</div>
          <div class="w-[20px] text-center text-surface-800">
            {{ data.capacidad }}
          </div>
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
.estado {
  font-weight: 500;
  width: fit-content;
  font-size: 9.5px;
  padding: 4px 8px !important;
  border-radius: 5px;
  text-transform: uppercase;
}
</style>
