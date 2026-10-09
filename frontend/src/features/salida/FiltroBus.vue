<!--
  Filtro para elegir el bus de una salida (ADR-027): distribución de asientos
  (molde de croquis) y clase de bus. Muestra el croquis elegido. Los buses
  compatibles los calcula quien lo usa (`core/salida/buses`).
-->
<template>
  <div class="flex flex-col gap-2">
    <div class="grid gap-2 md:grid-cols-2">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Distribución de asientos</span>
        <Select v-model="modelo.croquisId" :options="croquis" option-value="id" :option-label="etiqueta" show-clear fluid placeholder="Cualquiera" aria-label="Distribución de asientos">
          <template #option="{ option }">
            <span class="flex w-full items-baseline justify-between gap-3">
              <span>{{ etiquetaCroquis(option) }}</span>
              <span class="text-xs text-muted-color">{{ option.buses }} {{ option.buses === 1 ? 'bus' : 'buses' }}</span>
            </span>
          </template>
        </Select>
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Clase de bus</span>
        <Select v-model="modelo.claseId" :options="clases" option-value="id" option-label="nombre" show-clear fluid placeholder="Cualquiera" aria-label="Clase de bus" />
      </label>
    </div>
    <div v-if="elegido" class="overflow-x-auto rounded-md border border-surface-200 p-2 dark:border-surface-700">
      <BusMap :elementos="elegido.elementos" orientacion="horizontal" tamano="xs" class="justify-center" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { etiquetaCroquis, type FiltroBus } from '@/core/salida/buses'
import type { CroquisMolde, Opcion } from '@/core/salida/types'
import BusMap from '@/shared/bus-map/BusMap.vue'

const props = defineProps<{ croquis: CroquisMolde[]; clases: Opcion[] }>()
const modelo = defineModel<FiltroBus>({ required: true })

const elegido = computed(() => props.croquis.find((m) => m.id === modelo.value.croquisId) ?? null)
const etiqueta = (m: CroquisMolde) => etiquetaCroquis(m)
</script>
