<!--
  Reporte "Cuadre de venta de boletos": lo vendido en un día por usuario, por
  bus y salida, los prepagados, las ventas con tarjeta y los boletos anulados
  (PDF). La vista previa muestra las cifras antes de generar. Una estación o
  empresa asignada al usuario no se puede cambiar. Permiso `reporte.ventas`.
-->
<template>
  <ReporteLayout icono="request-quote-outline" descripcion="Cierre de caja del día: qué vendió cada usuario y cuánto se recibió, anuló y facturó." :problema="problema" :cargando="cargando" :error="error" :listo="!!resumen">
    <div class="grid gap-4 @lg:grid-cols-2">
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Fecha de venta</span>
        <DatePicker v-model="filtro.fecha" date-format="dd/mm/yy" show-icon icon-display="input" fluid :manual-input="false" input-id="cuadre-fecha" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Moneda</span>
        <Select v-model="filtro.moneda" :options="opciones?.monedas ?? []" option-value="sigla" :option-label="etiquetaMoneda" fluid placeholder="Elige la moneda" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Estación de venta</span>
        <estacion-select v-model="filtro.estacion" :estaciones="opciones?.estaciones ?? []" :propio="departamentoPropio" :disabled="!!opciones?.alcance.estacion" :show-clear="!opciones?.alcance.estacion" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Empresa</span>
        <Select v-model="filtro.empresa" :options="opciones?.empresas ?? []" option-value="id" option-label="nombre" :disabled="!!opciones?.alcance.empresa" :show-clear="!opciones?.alcance.empresa" fluid placeholder="Todas las empresas" />
      </label>
    </div>

    <template #acciones>
      <Button :disabled="!!problema || !opciones" :loading="generando" @click="generar" :label="adjuntar ? 'Generar vista previa' : 'Generar PDF'" class="shrink-0 whitespace-nowrap"><template #icon><icon name="picture-as-pdf-outline" class="mr-1.5" color="text-white" /></template></Button>
      <small v-if="!adjuntar" class="text-muted-color">Se abre en una pestaña nueva.</small>
    </template>

    <template #resumen>
      <template v-if="resumen">
        <div class="grid grid-cols-2 gap-3">
          <CifraReporte etiqueta="Boletos vendidos" :valor="resumen.boletos" />
          <CifraReporte etiqueta="Ventas" :valor="resumen.ventas" />
          <CifraReporte etiqueta="Recibido" :valor="importe(resumen.recibido, resumen.moneda)" destacada />
          <CifraReporte etiqueta="Facturado" :valor="importe(resumen.facturado, resumen.moneda)" destacada />
        </div>
        <CifraReporte v-if="resumen.anulado" etiqueta="Anulado" :valor="importe(resumen.anulado, resumen.moneda)" alerta />
        <p v-if="!resumen.boletos" class="m-0 text-sm text-muted-color">No hay ventas con estos parámetros; el reporte saldrá con las secciones vacías.</p>
        <ul v-else class="m-0 flex list-none flex-wrap gap-1.5 p-0 text-xs">
          <li v-for="e in secciones" :key="e" class="rounded-full bg-surface-100 px-2.5 py-1">{{ e }}</li>
        </ul>
      </template>
    </template>
  </ReporteLayout>
</template>

<script setup lang="ts">
import { fetchOpciones, fetchResumenCuadre } from '@/core/reporte/api'
import { cuadreInicial, cuadreQuery, errorCuadre, importe, type FiltroCuadre } from '@/core/reporte/filtro'
import type { Moneda, OpcionesReporte } from '@/core/reporte/types'
import { notify } from '@/core/notify'
import CifraReporte from './CifraReporte.vue'
import ReporteLayout from './ReporteLayout.vue'
import type { ReporteGenerado } from '@/shared/chat/integracion'
import { generarReporte, obtenerReporte } from './descarga'
import { useResumen } from './useResumen'

/** `adjuntar`: no abre el PDF, lo entrega en `generado` (selector del chat). */
const props = defineProps<{ adjuntar?: boolean }>()
const emit = defineEmits<{ generado: [reporte: ReporteGenerado] }>()

const opciones = ref<OpcionesReporte | null>(null)
const filtro = reactive<FiltroCuadre>({ fecha: null, estacion: null, empresa: null, moneda: null })
/** Departamento de la estación fija del usuario, si la tiene. */
const departamentoPropio = computed(() => opciones.value?.estaciones.find((e) => e.id === opciones.value?.alcance.estacion)?.departamento)
const generando = ref(false)

const query = computed(() => (opciones.value ? cuadreQuery(filtro) : null))
const problema = computed(() => (opciones.value ? errorCuadre(filtro) : null))
const { datos: resumen, cargando, error } = useResumen(() => query.value, fetchResumenCuadre)

const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`
const secciones = computed(() => {
  const r = resumen.value
  if (!r) return []
  return [
    plural(r.usuarios, 'usuario', 'usuarios'),
    plural(r.salidas, 'salida', 'salidas'),
    ...(r.prepagados ? [plural(r.prepagados, 'prepagado', 'prepagados')] : []),
    ...(r.tarjetas ? [plural(r.tarjetas, 'venta con tarjeta', 'ventas con tarjeta')] : []),
    ...(r.anulados ? [plural(r.anulados, 'boleto anulado', 'boletos anulados')] : []),
  ]
})

const etiquetaMoneda = (m: Moneda) => `${m.sigla} - ${m.nombre}`

onMounted(async () => {
  try {
    opciones.value = await fetchOpciones()
    Object.assign(filtro, cuadreInicial(opciones.value))
  } catch {
    notify.error('No se pudieron cargar las opciones del reporte.')
  }
})

async function generar() {
  if (!query.value) return
  generando.value = true
  try {
    const nombre = `cuadre_venta_boletos_${filtro.moneda}`
    if (!props.adjuntar) return void (await generarReporte('cuadre-venta-boletos', query.value, 'pdf', nombre))
    const blob = await obtenerReporte('cuadre-venta-boletos', query.value, 'pdf')
    if (blob) emit('generado', { blob, nombre: `${nombre}.pdf` })
  } finally {
    generando.value = false
  }
}
</script>
