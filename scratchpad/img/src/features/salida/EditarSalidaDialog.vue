<!--
  Editar una salida programada: ruta, bus (por distribución de asientos) y
  fecha/hora. Con asientos vendidos solo se cambia el bus, por otro con la
  misma distribución: los boletos pasan al mismo número (ADR-031). Con
  propagación, las futuras idénticas toman la ruta, el bus y la hora
  (cada una conserva su día). Emite `hecho` con el resultado del backend.
-->
<template>
  <Dialog :visible="!!salida" modal header="Editar salida" class="w-[min(32rem,calc(100vw-1rem))]" @update:visible="(v: boolean) => !v && emit('cerrar')">
    <div v-if="salida" class="flex flex-col gap-4">
      <p class="m-0 text-sm text-muted-color">Salida #{{ salida.id }} · {{ salida.ruta.nombre }}</p>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Ruta</span>
        <RutaSelect v-model="rutaId" />
        <span class="text-xs text-muted-color">La ruta solo se cambia si la salida no tiene asientos vendidos ni apartados.</span>
      </label>
      <div class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Distribución de asientos</span>
        <CroquisSelector :model-value="croquisId" :croquis="opciones.croquis" :buses="opciones.buses" :disabled="salida.vendidos > 0 && croquisId !== null" @update:model-value="elegirCroquis" />
      </div>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Bus</span>
        <Select :model-value="busId" :options="busesPara(opciones.buses, { croquisId }, busId)" :option-label="etiquetaBus" option-value="id" filter :filter-fields="['codigo', 'matricula']" fluid placeholder="Bus" :empty-message="croquisId ? 'Ningún bus con esta distribución' : 'Sin buses'" @update:model-value="elegirBus">
          <template #option="{ option }"><BusOpcionItem :bus="option" :empresas="empresas" :clases="clases" /></template>
        </Select>
        <span class="text-xs text-muted-color">{{ salida.vendidos ? `Tiene ${salida.vendidos} asiento(s) vendidos o apartados: elija un bus con la misma distribución y pasarán al mismo número.` : 'Sin asientos vendidos, el bus se cambia libremente.' }}</span>
      </label>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-medium text-muted-color">Fecha y hora</span>
        <DatePicker v-model="fecha" date-format="dd/mm/yy" show-time hour-format="24" :step-minute="5" show-icon fluid />
      </label>
      <PropagacionOpcion v-model="propagar" :salida-id="salida.id" verbo="cambiará" />
      <p v-if="propagar" class="m-0 text-xs text-muted-color">Las idénticas conservan su día y toman la ruta, el bus y la hora nuevos.</p>
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
import { busesPara } from '@/core/salida/buses'
import CroquisSelector from './CroquisSelector.vue'
import RutaSelect from './RutaSelect.vue'
import BusOpcionItem from './BusOpcionItem.vue'
import PropagacionOpcion from './PropagacionOpcion.vue'

const props = defineProps<{ salida: SalidaFila | null; opciones: OpcionesSalidas }>()
const emit = defineEmits<{ cerrar: []; hecho: [ResultadoOperacion] }>()

const rutaId = ref<number | null>(null)
const busId = ref<number | null>(null)
const croquisId = ref<number | null>(null)
const fecha = ref<Date | null>(null)
const propagar = ref(false)
const guardando = ref(false)

const empresas = computed(() => new Map(props.opciones.empresas.map((e) => [e.id, e.nombre])))
const clases = computed(() => new Map(props.opciones.clases.map((c) => [c.id, c.nombre])))
const etiquetaBus = (b: BusOpcion) => [b.codigo, b.matricula, b.empresaId ? empresas.value.get(b.empresaId) : null].filter(Boolean).join(' · ')
const busDe = (id: number | null) => (id ? (props.opciones.buses.find((b) => b.id === id) ?? null) : null)

/** Otra distribución: si el bus elegido no la tiene, se quita. */
function elegirCroquis(id: number | null) {
  croquisId.value = id
  if (busId.value && busDe(busId.value)?.croquisId !== id) busId.value = null
}

/** Elegir un bus fija la distribución a la suya. */
function elegirBus(id: number | null) {
  busId.value = id
  const bus = busDe(id)
  if (bus?.croquisId) croquisId.value = bus.croquisId
}

watch(
  () => props.salida,
  (s) => {
    if (!s) return
    rutaId.value = s.ruta.id
    busId.value = s.bus?.id ?? null
    croquisId.value = s.bus?.croquisId ?? null
    fecha.value = new Date(s.fecha)
  },
  { immediate: true },
)

/** Solo lo que cambió (o nada si no hubo cambios). */
const cambio = computed<CambioSalida | null>(() => {
  const s = props.salida
  if (!s || !fecha.value) return null
  const c: CambioSalida = { propagar: propagar.value }
  if (rutaId.value && rutaId.value !== s.ruta.id) c.rutaId = rutaId.value
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
