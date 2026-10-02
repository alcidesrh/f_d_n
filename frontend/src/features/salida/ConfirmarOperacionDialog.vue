<!--
  Confirmar anular (queda en la base de datos como cancelada) o eliminar
  (se borra) una salida, con la opción de propagarlo a las futuras idénticas.
-->
<template>
  <Dialog :visible="!!salida" modal :header="titulo" class="w-[min(30rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="salida" class="flex flex-col gap-3">
      <p class="m-0">
        ¿{{ operacion === 'anular' ? 'Anular' : 'Eliminar' }} la salida <b>#{{ salida.id }}</b> {{ salida.trayecto.ruta }} del
        <b>{{ fechaHora(salida.fecha) }}</b><template v-if="salida.bus"> (bus {{ salida.bus.codigo }})</template>?
      </p>
      <p class="m-0 text-xs text-muted-color">
        <template v-if="operacion === 'anular'">Deja de venderse y no sale, pero se conserva en la base de datos.</template>
        <template v-else>Se borra de la base de datos. Si tiene historial de boletos no se borra: anúlela.</template>
      </p>
      <PropagacionOpcion v-model="propagar" :salida-id="salida.id" :verbo="operacion === 'anular' ? 'anulará' : 'eliminará'" />
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button :label="operacion === 'anular' ? 'Anular' : 'Eliminar'" severity="danger" :loading="trabajando" @click="confirmar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { anularSalida, eliminarSalida } from '@/core/salida/api'
import type { ResultadoOperacion, SalidaFila } from '@/core/salida/types'
import { notify } from '@/core/notify'
import PropagacionOpcion from './PropagacionOpcion.vue'

const props = defineProps<{ salida: SalidaFila | null; operacion: 'anular' | 'eliminar' }>()
const emit = defineEmits<{ cerrar: []; hecho: [ResultadoOperacion] }>()

const propagar = ref(false)
const trabajando = ref(false)
const titulo = computed(() => (props.operacion === 'anular' ? 'Anular salida' : 'Eliminar salida'))
const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))

async function confirmar() {
  if (!props.salida) return
  trabajando.value = true
  try {
    const r = props.operacion === 'anular' ? await anularSalida(props.salida.id, propagar.value) : await eliminarSalida(props.salida.id, propagar.value)
    emit('hecho', r)
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    trabajando.value = false
  }
}
</script>
