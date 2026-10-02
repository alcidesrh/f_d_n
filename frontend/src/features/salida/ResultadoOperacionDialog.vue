<!--
  Las salidas que una operación (con o sin propagación) dejó sin tocar, por
  motivo: con asientos (reasignar o anular a mano, salida por salida), bus
  ocupado o con historial de boletos.
-->
<template>
  <Dialog :visible="!!resultado" modal header="Salidas sin cambios" class="w-[min(34rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="resultado" class="flex flex-col gap-4">
      <p class="m-0">{{ resumen(resultado, operacion).texto }}</p>
      <section v-for="g in omitidasPorMotivo(resultado)" :key="g.motivo" class="flex flex-col gap-2">
        <Message :severity="g.motivo === 'asientos' ? 'warn' : 'secondary'" :closable="false" class="!text-sm">{{ g.texto }}</Message>
        <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
          <li v-for="s in g.salidas" :key="s.id ?? s.fecha" class="flex flex-wrap gap-x-2">
            <span class="font-medium">#{{ s.id }}</span>
            <span>{{ fechaHora(s.fecha) }}</span>
            <span class="text-muted-color">{{ s.ruta }}<template v-if="s.bus"> · bus {{ s.bus }}</template></span>
            <span v-if="s.asientos" class="text-orange-600 dark:text-orange-400">{{ s.asientos }} asiento(s)</span>
            <span v-if="s.choque" class="basis-full text-xs text-muted-color">Choca con #{{ s.choque.id }} {{ s.choque.ruta }}, {{ fechaHora(s.choque.fecha) }}</span>
          </li>
        </ul>
      </section>
    </div>
    <template #footer>
      <Button label="Entendido" @click="emit('cerrar')" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { omitidasPorMotivo, resumen, type Operacion } from '@/core/salida/resultado'
import type { ResultadoOperacion } from '@/core/salida/types'

defineProps<{ resultado: ResultadoOperacion | null; operacion: Operacion }>()
const emit = defineEmits<{ cerrar: [] }>()

const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>
