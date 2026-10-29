<!--
  Reporte "Cuadre de voucher": los boletos emitidos sin cobro (voucher o
  cortesía) en el rango de fechas de venta, agrupados por estación y día, con
  su importe; en PDF. Una estación o empresa asignada al usuario no se puede
  cambiar. Permiso `reporte.ventas`.
-->
<template>
  <ReporteLayout icono="confirmation-number-outline" descripcion="Boletos emitidos como voucher o cortesía, por estación y día de venta." :problema="problema" :cargando="cargando" :error="error" :listo="!!resumen">
    <div class="grid gap-4 @lg:grid-cols-2">
      <label class="flex flex-col gap-1.5 @lg:col-span-2">
        <span class="text-sm font-medium">Fecha de venta (un día o un rango)</span>
        <DatePicker v-model="filtro.rango" selection-mode="range" date-format="dd/mm/yy" show-icon icon-display="input" show-button-bar fluid :manual-input="false" :number-of-months="2" input-id="voucher-rango" placeholder="Elige un día o un rango de fechas" />
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
      <Button :disabled="!!problema || !opciones" :loading="generando" @click="generar" :label="adjuntar ? 'Vista previa PDF' : 'Generar PDF'" class="shrink-0 whitespace-nowrap"><template #icon><icon name="picture-as-pdf-outline" class="mr-1.5" color="text-inherit" /></template></Button>
    </template>

    <template #resumen>
      <template v-if="resumen">
        <div class="grid grid-cols-2 gap-3">
          <CifraReporte etiqueta="Vouchers" :valor="resumen.cantidad" />
          <CifraReporte etiqueta="Días con vouchers" :valor="resumen.dias" />
        </div>
        <CifraReporte v-for="t in resumen.totales" :key="t.moneda" :etiqueta="`Importe ${t.moneda} · ${t.cantidad} boletos`" :valor="importe(t.total, t.moneda)" destacada />
        <p v-if="!resumen.cantidad" class="m-0 text-sm text-muted-color">No hay vouchers con estos parámetros.</p>
      </template>
    </template>
  </ReporteLayout>
</template>

<script setup lang="ts">
import { fetchOpciones, fetchResumenVoucher } from '@/core/reporte/api'
import { aDia, errorVoucher, importe, voucherInicial, voucherQuery, type FiltroVoucher } from '@/core/reporte/filtro'
import type { OpcionesReporte } from '@/core/reporte/types'
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
const filtro = reactive<FiltroVoucher>({ rango: null, estacion: null, empresa: null })
/** Departamento de la estación fija del usuario, si la tiene. */
const departamentoPropio = computed(() => opciones.value?.estaciones.find((e) => e.id === opciones.value?.alcance.estacion)?.departamento)
const generando = ref(false)

// Con el primer día elegido y el segundo pendiente, el reporte es de ese día.
const query = computed(() => (opciones.value ? voucherQuery(filtro) : null))
const problema = computed(() => (opciones.value ? errorVoucher(filtro) : null))
const { datos: resumen, cargando, error } = useResumen(() => query.value, fetchResumenVoucher)

onMounted(async () => {
  try {
    opciones.value = await fetchOpciones()
    Object.assign(filtro, voucherInicial(opciones.value))
  } catch {
    notify.error('No se pudieron cargar las opciones del reporte.')
  }
})

async function generar() {
  if (!query.value) return
  generando.value = true
  try {
    const [desde, hasta] = filtro.rango!
    const nombre = `cuadre_voucher_${aDia(desde!)}_${aDia(hasta ?? desde!)}`
    if (!props.adjuntar) return void (await generarReporte('cuadre-voucher', query.value, 'pdf', nombre))
    const blob = await obtenerReporte('cuadre-voucher', query.value, 'pdf')
    if (blob) emit('generado', { blob, nombre: `${nombre}.pdf` })
  } finally {
    generando.value = false
  }
}
</script>
