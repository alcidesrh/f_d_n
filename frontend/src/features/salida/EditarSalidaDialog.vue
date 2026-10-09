<!--
  Editar una salida programada: trayecto, bus y fecha/hora. Con
  propagación, las futuras idénticas toman el trayecto, el bus y la hora
  (cada una conserva su día). Emite `hecho` con el resultado del backend.
-->
<template>
  <Dialog :visible="!!salida" modal header="Editar salida" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="salida" class="flex flex-col gap-4">
      <p class="m-0 text-sm text-muted-color">Salida #{{ salida.id }} · {{ salida.trayecto.ruta }}</p>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Trayecto</span>
        <Select v-model="trayectoId" :options="opciones.trayectos" option-label="ruta" option-value="id" filter fluid :virtual-scroller-options="{ itemSize: 38 }" />
      </label>
      <FiltroBus v-model="filtroBus" :croquis="opciones.croquis" :clases="opciones.clases" />
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Bus</span>
        <Select v-model="busId" :options="busesCompatibles(opciones.buses, filtroBus, busId)" :option-label="etiquetaBus" option-value="id" filter :filter-fields="['codigo', 'matricula']" fluid placeholder="Bus" />
        <span class="text-xs text-muted-color">El bus solo se cambia si la salida no tiene asientos vendidos ni apartados.</span>
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Fecha y hora</span>
        <DatePicker v-model="fecha" date-format="dd/mm/yy" show-time hour-format="24" show-icon fluid />
      </label>
      <PropagacionOpcion v-model="propagar" :salida-id="salida.id" verbo="cambiará" />
      <p v-if="propagar" class="m-0 text-xs text-muted-color">Las idénticas conservan su día y toman el trayecto, el bus y la hora nuevos.</p>
    </div>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text @click="emit('cerrar')" />
      <Button label="Guardar" :loading="guardando" :disabled="!cambio" @click="guardar" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { editarSalida } from '@/core/salida/api'
import { aFechaHora } from '@/core/salida/filtro'
import type { BusOpcion, CambioSalida, OpcionesSalidas, ResultadoOperacion, SalidaFila } from '@/core/salida/types'
import { notify } from '@/core/notify'
import { busesCompatibles, filtroBusVacio, filtroDeBus } from '@/core/salida/buses'
import FiltroBus from './FiltroBus.vue'
import PropagacionOpcion from './PropagacionOpcion.vue'

const props = defineProps<{ salida: SalidaFila | null; opciones: OpcionesSalidas }>()
const emit = defineEmits<{ cerrar: []; hecho: [ResultadoOperacion] }>()

const trayectoId = ref<number | null>(null)
const busId = ref<number | null>(null)
const fecha = ref<Date | null>(null)
const propagar = ref(false)
/** Arranca con el croquis y la clase del bus actual: sugiere uno equivalente. */
const filtroBus = ref(filtroBusVacio())
const guardando = ref(false)

const empresas = computed(() => new Map(props.opciones.empresas.map((e) => [e.id, e.nombre])))
const etiquetaBus = (b: BusOpcion) => [b.codigo, b.matricula, b.empresaId ? empresas.value.get(b.empresaId) : null].filter(Boolean).join(' · ')

watch(
  () => props.salida,
  (s) => {
    if (!s) return
    trayectoId.value = s.trayecto.id
    busId.value = s.bus?.id ?? null
    filtroBus.value = filtroDeBus(props.opciones.buses.find((b) => b.id === s.bus?.id))
    fecha.value = new Date(s.fecha)
  },
  { immediate: true },
)

/** Solo lo que cambió (o nada si no hubo cambios). */
const cambio = computed<CambioSalida | null>(() => {
  const s = props.salida
  if (!s || !fecha.value) return null
  const c: CambioSalida = { propagar: propagar.value }
  if (trayectoId.value && trayectoId.value !== s.trayecto.id) c.trayectoId = trayectoId.value
  if (busId.value && busId.value !== s.bus?.id) c.busId = busId.value
  const f = aFechaHora(fecha.value)
  if (f !== aFechaHora(new Date(s.fecha))) c.fecha = f
  return Object.keys(c).length > 1 ? c : null
})

async function guardar() {
  if (!props.salida || !cambio.value) return
  guardando.value = true
  try {
    emit('hecho', await editarSalida(props.salida.id, cambio.value))
  } catch (e) {
    notify.error(e instanceof Error ? e.message : String(e))
  } finally {
    guardando.value = false
  }
}
</script>
