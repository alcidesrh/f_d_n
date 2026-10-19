<!--
  Manifiesto de carga de encomiendas (ADR-035): se elige la salida (de las de
  un día) y, si se quiere, solo lo que va a un destino; el PDF lista lo que
  lleva por destino con el importe y espacio para la firma de entrega. El
  mismo PDF sale de «Manifiesto» en Procesar por salida. Permiso
  `encomienda.ver` o `reporte.encomiendas`.
-->
<template>
  <ReporteLayout icono="local-shipping-outline" descripcion="Lo que lleva la salida en bodega, por destino, para entregar al piloto." :cargando="cargando" :error="error" :listo="!!resumen">
    <div class="grid gap-4 @lg:grid-cols-[11rem_minmax(0,1fr)]">
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Fecha de salida</span>
        <DatePicker v-model="fecha" date-format="dd/mm/yy" show-icon icon-display="input" :manual-input="false" fluid input-id="manifiesto-enc-fecha" @update:model-value="salidaId = null" />
      </label>
      <label class="flex min-w-0 flex-col gap-1.5">
        <span class="text-sm font-medium">Salida</span>
        <SalidaSelect v-model="salidaId" :desde="dia" :hasta="dia" input-id="manifiesto-enc-salida" />
      </label>
      <label class="flex flex-col gap-1.5 @lg:col-span-2">
        <span class="text-sm font-medium">Estación de destino <span class="font-normal text-muted-color">(opcional)</span></span>
        <estacion-select v-model="destino" :estaciones="opciones?.enclaves ?? []" show-clear placeholder="Todos los destinos" />
      </label>
    </div>

    <template #acciones>
      <Button :label="adjuntar ? 'Vista previa PDF' : 'Generar PDF'" :disabled="!query" :loading="generando" class="shrink-0 whitespace-nowrap" @click="generar">
        <template #icon><icon name="picture-as-pdf-outline" class="mr-1.5" color="text-white" /></template>
      </Button>
    </template>

    <template #resumen>
      <div v-if="resumen" class="grid grid-cols-2 gap-3">
        <CifraReporte v-for="c in cifras" :key="c.etiqueta" :etiqueta="c.etiqueta" :valor="c.valor" :destacada="c.destacada" />
      </div>
      <p v-if="resumen && !resumen.cantidad" class="m-0 text-sm text-muted-color">La salida no lleva encomiendas{{ destino ? " para ese destino" : "" }}.</p>
    </template>
  </ReporteLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { cifrasEncomienda, fetchArchivoEncomienda, fetchOpcionesEncomienda, fetchResumenEncomienda, type OpcionesReporteEncomienda } from '@/core/reporte/encomienda'
import { aDia } from '@/core/reporte/filtro'
import { notify } from '@/core/notify'
import type { ReporteGenerado } from '@/shared/chat/integracion'
import SalidaSelect from '@/shared/ui/SalidaSelect.vue'
import CifraReporte from './CifraReporte.vue'
import ReporteLayout from './ReporteLayout.vue'
import { entregarArchivo } from './descarga'
import { useResumen } from './useResumen'

const props = defineProps<{ adjuntar?: boolean }>()
const emit = defineEmits<{ generado: [reporte: ReporteGenerado] }>()

const opciones = ref<OpcionesReporteEncomienda | null>(null)
const fecha = ref<Date | null>(new Date())
const dia = computed(() => aDia(fecha.value ?? new Date()))
const salidaId = ref<number | null>(null)
const destino = ref<number | null>(null)
const generando = ref(false)

const query = computed(() => (salidaId.value ? `salida=${salidaId.value}${destino.value ? `&destino=${destino.value}` : ''}` : null))
const { datos: resumen, cargando, error } = useResumen(
  () => query.value,
  (q) => fetchResumenEncomienda('manifiesto', q),
)
const cifras = computed(() => (resumen.value ? cifrasEncomienda('manifiesto', resumen.value) : []))

onMounted(async () => {
  try {
    opciones.value = await fetchOpcionesEncomienda()
  } catch {
    notify.error('No se pudieron cargar las estaciones.')
  }
})

async function generar() {
  if (!query.value) return
  generando.value = true
  try {
    const blob = await fetchArchivoEncomienda('manifiesto', query.value, 'pdf')
    const nombre = `manifiesto_encomiendas_${salidaId.value}`
    if (props.adjuntar) emit('generado', { blob, nombre: `${nombre}.pdf` })
    else entregarArchivo(blob, 'pdf', nombre)
  } catch (e) {
    notify.error(e instanceof Error && e.message && !e.message.startsWith('HTTP ') ? e.message : 'No se pudo generar el manifiesto.')
  } finally {
    generando.value = false
  }
}
</script>
