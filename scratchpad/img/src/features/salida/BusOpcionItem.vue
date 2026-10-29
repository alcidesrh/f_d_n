<!--
  Un bus en la lista para elegir: código y matrícula; debajo su empresa y su
  clase, con ícono para no confundirlas (hay clases con nombre de empresa,
  "Clase Oro / Maya de Oro").
-->
<template>
  <div class="flex min-w-0 flex-col">
    <span><strong>{{ bus.codigo }}</strong><span v-if="bus.matricula" class="text-muted-color"> · {{ bus.matricula }}</span></span>
    <span class="flex flex-wrap gap-x-3 text-xs text-muted-color">
      <span v-if="empresa" class="inline-flex items-center gap-1"><icon name="apartment" />{{ empresa }}</span>
      <span v-if="clase" class="inline-flex items-center gap-1"><icon name="airline-seat-recline-extra" />{{ clase }}</span>
    </span>
  </div>
</template>

<script setup lang="ts">
import type { BusOpcion } from '@/core/salida/types'

const props = defineProps<{ bus: BusOpcion; empresas: ReadonlyMap<number, string>; clases: ReadonlyMap<number, string> }>()
const empresa = computed(() => (props.bus.empresaId ? props.empresas.get(props.bus.empresaId) : null))
const clase = computed(() => (props.bus.claseId ? props.clases.get(props.bus.claseId) : null))
</script>
