<!--
  Reportes de encomiendas (ADR-035) con parámetros de formulario: cuadre,
  detalle de factura, detalle general, guías internas y pendientes de
  entregar. Qué pide cada uno está en `REPORTES_ENCOMIENDA`
  (`core/reporte/encomienda.ts`); el manifiesto, que se elige por salida,
  tiene su pantalla. Una estación o empresa asignada al usuario no se puede
  cambiar. Permisos: `reporte.encomiendas` (caja) o `encomienda.ver`.
-->
<template>
  <ReporteLayout :icono="cfg.icono" :descripcion="cfg.descripcion" :problema="sinPermiso ?? problema" :cargando="cargando" :error="error" :listo="!!resumen">
    <div class="grid gap-4 @lg:grid-cols-2">
      <label v-if="cfg.fechas" class="flex flex-col gap-1.5" :class="{ '@lg:col-span-2': cfg.fechas === 'rango' }">
        <span class="text-sm font-medium">{{ cfg.etiquetaFecha }}</span>
        <DatePicker v-if="cfg.fechas === 'rango'" v-model="filtro.rango" selection-mode="range" date-format="dd/mm/yy" show-icon icon-display="input" show-button-bar fluid :manual-input="false" :number-of-months="2" :input-id="`enc-${tipo}-rango`" placeholder="Elige un día o un rango de fechas" />
        <DatePicker v-else v-model="dia" date-format="dd/mm/yy" show-icon icon-display="input" fluid :manual-input="false" :input-id="`enc-${tipo}-dia`" />
      </label>
      <label v-if="cfg.campos.includes('estacion')" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">{{ cfg.etiquetaEstacion }}</span>
        <estacion-select v-model="filtro.estacion" :estaciones="opciones?.estaciones ?? []" :propio="departamentoPropio" :disabled="!!opciones?.alcance.estacion" :show-clear="!opciones?.alcance.estacion" placeholder="Todas las estaciones" />
      </label>
      <label v-if="cfg.campos.includes('empresa')" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Empresa</span>
        <Select v-model="filtro.empresa" :options="opciones?.empresas ?? []" option-value="id" option-label="nombre" :disabled="!!opciones?.alcance.empresa" :show-clear="!opciones?.alcance.empresa" fluid placeholder="Todas las empresas" />
      </label>
      <label v-if="cfg.campos.includes('origen')" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Estación de origen</span>
        <estacion-select v-model="filtro.origen" :estaciones="opciones?.enclaves ?? []" :propio="departamentoPropio" show-clear placeholder="Todos los orígenes" />
      </label>
      <label v-if="cfg.campos.includes('destino')" class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Estación de destino</span>
        <estacion-select v-model="filtro.destino" :estaciones="opciones?.enclaves ?? []" :propio="departamentoPropio" show-clear placeholder="Todos los destinos" />
      </label>
      <div v-if="cfg.campos.includes('casillas')" class="flex flex-col gap-2.5 @lg:col-span-2 @lg:flex-row @lg:gap-8">
        <label class="flex cursor-pointer items-center gap-2 text-sm"><Checkbox v-model="filtro.soloFacturado" binary :input-id="`enc-${tipo}-facturado`" />Mostrar solo las pagadas (facturadas al recibir)</label>
        <label class="flex cursor-pointer items-center gap-2 text-sm"><Checkbox v-model="filtro.soloPorCobrar" binary :input-id="`enc-${tipo}-cobrar`" />Mostrar solo las por cobrar</label>
      </div>
    </div>

    <template #acciones>
      <Button v-for="f in cfg.formatos" :key="f" :severity="f === 'pdf' ? undefined : 'secondary'" :outlined="f !== 'pdf'" :disabled="!!problema || !!sinPermiso || !opciones" :loading="generando === f" class="shrink-0 whitespace-nowrap" :label="etiquetaBoton(f)" @click="generar(f)">
        <template #icon><icon :name="f === 'pdf' ? 'picture-as-pdf-outline' : 'table-chart-outline'" class="mr-1.5" :color="f === 'pdf' ? 'text-inherit' : 'text-primary'" /></template>
      </Button>
    </template>

    <template #resumen>
      <div v-if="resumen" class="grid grid-cols-2 gap-3">
        <CifraReporte v-for="c in cifras" :key="c.etiqueta" :etiqueta="c.etiqueta" :valor="c.valor" :destacada="c.destacada" :alerta="c.alerta" :class="{ 'col-span-2': c.destacada && cifras.filter((x) => x.destacada).length === 1 }" />
      </div>
    </template>
  </ReporteLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { cifrasEncomienda, encomiendaQuery, errorEncomienda, fetchArchivoEncomienda, fetchOpcionesEncomienda, fetchResumenEncomienda, filtroEncomiendaInicial, nombreArchivo, REPORTES_ENCOMIENDA, type FiltroEncomienda, type OpcionesReporteEncomienda } from '@/core/reporte/encomienda'
import type { FormatoReporte } from '@/core/reporte/types'
import { notify } from '@/core/notify'
import type { ReporteGenerado } from '@/shared/chat/integracion'
import CifraReporte from './CifraReporte.vue'
import ReporteLayout from './ReporteLayout.vue'
import { entregarArchivo } from './descarga'
import { useResumen } from './useResumen'

type Tipo = keyof typeof REPORTES_ENCOMIENDA

/** `adjuntar`: no abre ni descarga el archivo, lo entrega en `generado` (selector del chat). */
const props = defineProps<{ tipo: Tipo; adjuntar?: boolean }>()
const emit = defineEmits<{ generado: [reporte: ReporteGenerado] }>()

const cfg = computed(() => REPORTES_ENCOMIENDA[props.tipo])
const opciones = ref<OpcionesReporteEncomienda | null>(null)
const filtro = reactive<FiltroEncomienda>({ rango: null, estacion: null, empresa: null, origen: null, destino: null, soloFacturado: false, soloPorCobrar: false })
/** Los de un día usan el primer elemento del rango. */
const dia = computed({
  get: () => filtro.rango?.[0] ?? null,
  set: (d: Date | null) => (filtro.rango = d ? [d, d] : null),
})
const departamentoPropio = computed(() => opciones.value?.estaciones.find((e) => e.id === opciones.value?.alcance.estacion)?.departamento)
const generando = ref<FormatoReporte | null>(null)

const sinPermiso = computed(() => (opciones.value && cfg.value.caja && !opciones.value.caja ? 'Este reporte de caja requiere el permiso de reportes de encomiendas.' : null))
const problema = computed(() => (opciones.value ? errorEncomienda(cfg.value, filtro) : null))
const query = computed(() => (opciones.value && !sinPermiso.value ? encomiendaQuery(cfg.value, filtro) : null))
const { datos: resumen, cargando, error } = useResumen(
  () => query.value,
  (q) => fetchResumenEncomienda(props.tipo, q),
)
const cifras = computed(() => (resumen.value ? cifrasEncomienda(props.tipo, resumen.value) : []))

const etiquetaBoton = (f: FormatoReporte) => (f === 'pdf' ? (props.adjuntar ? 'Vista previa PDF' : 'Generar PDF') : props.adjuntar ? 'Generar Excel' : 'Descargar Excel')

async function cargarOpciones() {
  try {
    opciones.value ??= await fetchOpcionesEncomienda()
    Object.assign(filtro, filtroEncomiendaInicial(opciones.value))
  } catch {
    notify.error('No se pudieron cargar las opciones del reporte.')
  }
}
onMounted(cargarOpciones)
// La misma pantalla sirve a varios reportes: al cambiar de uno a otro, parámetros nuevos.
watch(() => props.tipo, cargarOpciones)

async function generar(formato: FormatoReporte) {
  if (!query.value || !opciones.value) return
  generando.value = formato
  try {
    const blob = await fetchArchivoEncomienda(props.tipo, query.value, formato)
    const nombre = nombreArchivo(props.tipo, filtro, opciones.value.hoy)
    if (props.adjuntar) emit('generado', { blob, nombre: `${nombre}.${formato}` })
    else entregarArchivo(blob, formato, nombre)
  } catch (e) {
    notify.error(e instanceof Error && e.message && !e.message.startsWith('HTTP ') ? e.message : 'No se pudo generar el reporte.')
  } finally {
    generando.value = null
  }
}
</script>
