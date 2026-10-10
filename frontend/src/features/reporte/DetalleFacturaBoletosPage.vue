<!--
  Reporte "Detalle de factura de boletos": un renglón por boleto vendido en el
  rango de fechas, con su factura electrónica (DTE), autorización de tarjeta y
  referencia externa; en PDF o Excel. Una estación o empresa asignada al
  usuario no se puede cambiar. Permiso `reporte.ventas`.
-->
<template>
  <ReporteLayout icono="receipt-long-outline" descripcion="Facturas de los boletos vendidos, para conciliar con tarjetas y con el certificador." :problema="problema" :cargando="cargando" :error="error" :listo="!!resumen">
    <div class="grid gap-4 @lg:grid-cols-2">
      <label class="flex flex-col gap-1.5 @lg:col-span-2">
        <span class="text-sm font-medium">Fecha de venta (un día o un rango)</span>
        <DatePicker v-model="filtro.rango" selection-mode="range" date-format="dd/mm/yy" show-icon icon-display="input" show-button-bar fluid :manual-input="false" :number-of-months="2" input-id="detalle-rango" placeholder="Elige un día o un rango de fechas" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Estación de venta</span>
        <estacion-select v-model="filtro.estacion" :estaciones="opciones?.estaciones ?? []" :propio="departamentoPropio" :disabled="!!opciones?.alcance.estacion" :show-clear="!opciones?.alcance.estacion" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Empresa</span>
        <Select v-model="filtro.empresa" :options="opciones?.empresas ?? []" option-value="id" option-label="nombre" :disabled="!!opciones?.alcance.empresa" :show-clear="!opciones?.alcance.empresa" fluid placeholder="Todas las empresas" />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Autorización de tarjeta</span>
        <InputText v-model="filtro.autorizacion" maxlength="50" placeholder="Número de autorización" fluid />
      </label>
      <label class="flex flex-col gap-1.5">
        <span class="text-sm font-medium">Referencia externa</span>
        <InputText v-model="filtro.referencia" maxlength="100" placeholder="Referencia de la pasarela" fluid />
      </label>
      <div class="flex flex-col gap-2.5 @lg:col-span-2 @lg:flex-row @lg:gap-8">
        <label class="flex cursor-pointer items-center gap-2 text-sm"><Checkbox v-model="filtro.soloTarjetas" binary input-id="solo-tarjetas" />Mostrar solo autorizaciones de tarjetas</label>
        <label class="flex cursor-pointer items-center gap-2 text-sm"><Checkbox v-model="filtro.soloReferencias" binary input-id="solo-referencias" />Mostrar solo referencias externas</label>
      </div>
    </div>

    <template #acciones>
      <Button :disabled="!!problema || !opciones" :loading="generando === 'pdf'" @click="generar('pdf')" :label="adjuntar ? 'Vista previa PDF' : 'Generar PDF'" class="shrink-0 whitespace-nowrap"><template #icon><icon name="picture-as-pdf-outline" class="mr-1.5" color="text-white" /></template></Button>
      <Button severity="secondary" outlined :disabled="!!problema || !opciones" :loading="generando === 'xlsx'" @click="generar('xlsx')" :label="adjuntar ? 'Generar Excel' : 'Descargar Excel'" class="shrink-0 whitespace-nowrap"><template #icon><icon name="table-chart-outline" class="mr-1.5" color="text-primary" /></template></Button>
    </template>

    <template #resumen>
      <template v-if="resumen">
        <div class="grid grid-cols-2 gap-3">
          <CifraReporte etiqueta="Boletos" :valor="resumen.cantidad" />
          <CifraReporte etiqueta="Con tarjeta" :valor="resumen.conTarjeta" />
        </div>
        <CifraReporte v-for="t in resumen.totales" :key="t.moneda" :etiqueta="`Total ${t.moneda} · ${t.cantidad} boletos`" :valor="importe(t.total, t.moneda)" destacada />
        <p v-if="!resumen.cantidad" class="m-0 text-sm text-muted-color">No hay boletos con estos parámetros.</p>
        <p v-else-if="resumen.sinFactura" class="m-0 flex items-start gap-1.5 text-sm text-muted-color">
          <icon name="info-outline" class="mt-0.5" />{{ resumen.sinFactura }} {{ resumen.sinFactura === 1 ? 'boleto aparece' : 'boletos aparecen' }} sin número DTE (pendientes de certificar o sin factura).
        </p>
      </template>
    </template>
  </ReporteLayout>
</template>

<script setup lang="ts">
import { fetchOpciones, fetchResumenDetalle } from '@/core/reporte/api'
import { aDia, detalleInicial, detalleQuery, errorDetalle, importe, type FiltroDetalle } from '@/core/reporte/filtro'
import type { FormatoReporte, OpcionesReporte } from '@/core/reporte/types'
import { notify } from '@/core/notify'
import CifraReporte from './CifraReporte.vue'
import ReporteLayout from './ReporteLayout.vue'
import type { ReporteGenerado } from '@/shared/chat/integracion'
import { generarReporte, obtenerReporte } from './descarga'
import { useResumen } from './useResumen'

/** `adjuntar`: no abre ni descarga el archivo, lo entrega en `generado` (selector del chat). */
const props = defineProps<{ adjuntar?: boolean }>()
const emit = defineEmits<{ generado: [reporte: ReporteGenerado] }>()

const opciones = ref<OpcionesReporte | null>(null)
const filtro = reactive<FiltroDetalle>({ rango: null, estacion: null, empresa: null, autorizacion: '', referencia: '', soloTarjetas: false, soloReferencias: false })
/** Departamento de la estación fija del usuario, si la tiene. */
const departamentoPropio = computed(() => opciones.value?.estaciones.find((e) => e.id === opciones.value?.alcance.estacion)?.departamento)
const generando = ref<FormatoReporte | null>(null)

// Con el primer día elegido y el segundo pendiente, el reporte es de ese día.
const query = computed(() => (opciones.value ? detalleQuery(filtro) : null))
const problema = computed(() => (opciones.value ? errorDetalle(filtro) : null))
const { datos: resumen, cargando, error } = useResumen(() => query.value, fetchResumenDetalle)

onMounted(async () => {
  try {
    opciones.value = await fetchOpciones()
    Object.assign(filtro, detalleInicial(opciones.value))
  } catch {
    notify.error('No se pudieron cargar las opciones del reporte.')
  }
})

async function generar(formato: FormatoReporte) {
  if (!query.value) return
  generando.value = formato
  try {
    const [desde, hasta] = filtro.rango!
    const nombre = `detalle_factura_boletos_${aDia(desde!)}_${aDia(hasta ?? desde!)}`
    if (!props.adjuntar) return void (await generarReporte('detalle-factura-boletos', query.value, formato, nombre))
    const blob = await obtenerReporte('detalle-factura-boletos', query.value, formato)
    if (blob) emit('generado', { blob, nombre: `${nombre}.${formato}` })
  } finally {
    generando.value = null
  }
}
</script>
