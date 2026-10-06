<!-- TEMPORAL (ver `estado.ts`): casilla "usar datos del sistema anterior". -->
<template>
  <label class="flex w-fit cursor-pointer items-center gap-2 rounded-lg border border-dashed border-amber-500 bg-amber-500/10 px-3 py-2 text-sm">
    <Checkbox v-model="usarLegado" binary input-id="usar-legado" @update:model-value="emit('cambio', !!$event)" />
    <span class="font-medium">Usar datos del sistema anterior (prueba)</span>
    <Tag severity="warn" value="Solo lectura" />
  </label>
</template>

<script setup lang="ts">
import { usarLegado } from './estado'

const emit = defineEmits<{ cambio: [activo: boolean] }>()
</script>
