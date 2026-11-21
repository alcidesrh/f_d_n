<!--
  Venta registrada: resumen y reimpresión del ticket / PDF. Con asientos de
  varias salidas, una venta por cada una: cada comprobante con su factura,
  su ticket y su PDF.
-->
<template>
  <Dialog
    :visible="comprobantes.length > 0"
    modal
    :header="titulo"
    :style="{ width: '30rem' }"
    @update:visible="!$event && emit('cerrar')"
  >
    <div class="flex flex-col gap-3">
      <div v-for="c in comprobantes" :key="c.id" class="flex flex-col gap-2" :class="{ 'venta-registrada': varias }">
        <Message :severity="severidad(c)" :closable="false">{{ mensaje(c) }}</Message>
        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
          <template v-if="varias && c.origen && c.destino">
            <dt class="text-muted-color">Viaje</dt>
            <dd>{{ c.origen.nombre }} → {{ c.destino.nombre }}<span v-if="c.salida" class="text-muted-color"> · {{ fechaCorta(c.salida.salidaOrigen) }}</span></dd>
          </template>
          <dt class="text-muted-color">Venta</dt>
          <dd class="font-mono">{{ c.codigoBarras }}</dd>
          <template v-if="c.factura">
            <dt class="text-muted-color">DTE</dt>
            <dd>{{ c.factura.numero }} · serie {{ c.factura.serie }}</dd>
          </template>
          <template v-if="c.numeroAcceso">
            <dt class="text-muted-color">No. acceso</dt>
            <dd class="font-mono">{{ c.numeroAcceso }}</dd>
          </template>
          <dt class="text-muted-color">Asientos</dt>
          <dd>{{ c.boletos.map((b) => b.asiento).join(', ') }}</dd>
          <dt class="text-muted-color">Total</dt>
          <dd class="font-semibold">{{ c.total.texto }}</dd>
        </dl>
        <div class="flex flex-wrap justify-end gap-2">
          <a v-if="c.factura?.urlPdf" :href="c.factura.urlPdf" target="_blank" rel="noopener" class="no-underline">
            <Button label="Factura (DTE)" severity="secondary" size="small" text />
          </a>
          <Button label="PDF" severity="secondary" size="small" outlined :loading="descargando === c.id" @click="pdf(c)">
            <template #icon><icon name="picture-as-pdf-outline" class="mr-1" /></template>
          </Button>
          <Button label="Imprimir ticket" severity="secondary" size="small" outlined @click="imprimirTicket(c)">
            <template #icon><icon name="print-outline" class="mr-1" /></template>
          </Button>
        </div>
      </div>
      <p v-if="varias" class="m-0 text-right text-sm">
        <span class="text-muted-color">Total cobrado</span>
        <span class="ml-2 font-semibold tabular-nums">{{ total }}</span>
      </p>
    </div>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <Button v-if="varias" label="Imprimir todos" severity="secondary" outlined @click="imprimirTodos">
          <template #icon><icon name="print-outline" class="mr-1" /></template>
        </Button>
        <Button :label="reasignacion ? 'Listo' : 'Nueva venta'" autofocus @click="emit('cerrar')" />
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { fetchComprobantePdf } from '@/core/venta/api'
import { sumarImportes } from '@/core/venta/modelo'
import { notify } from '@/core/notify'
import type { Comprobante } from '@/core/venta/types'
import { imprimirTicket } from '@/shared/boleto/ticket'
import { fechaCorta } from './store'

const props = defineProps<{ comprobantes: Comprobante[]; reasignacion?: boolean }>()
const emit = defineEmits<{ cerrar: [] }>()
const descargando = ref<number | null>(null)

const varias = computed(() => props.comprobantes.length > 1)
const titulo = computed(() => {
  if (props.reasignacion) return 'Reasignación registrada'
  return varias.value ? `${props.comprobantes.length} ventas registradas` : 'Venta registrada'
})
const total = computed(() => sumarImportes(props.comprobantes.map((c) => c.total))?.texto ?? '—')

const severidad = (c: Comprobante) =>
  !props.reasignacion && c.estadoFacturacion === 'pendiente' ? 'warn' : 'success'
function mensaje(c: Comprobante) {
  if (props.reasignacion) return 'Boleto(s) reasignado(s). Imprima el ticket nuevo y entréguelo al pasajero.'
  if (c.cortesia) return 'Cortesía emitida.'
  if (c.estadoFacturacion === 'certificada') return 'Factura electrónica certificada.'
  if (c.estadoFacturacion === 'pendiente')
    return 'Venta en contingencia: la factura se certificará después.'
  return c.canal === 'agencia'
    ? 'Venta de agencia registrada (se descontó del saldo).'
    : 'Venta registrada.'
}

async function imprimirTodos() {
  for (const c of props.comprobantes) await imprimirTicket(c)
}

async function pdf(c: Comprobante) {
  descargando.value = c.id
  try {
    const url = URL.createObjectURL(await fetchComprobantePdf(c.id))
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch {
    notify.error('No se pudo generar el PDF.')
  } finally {
    descargando.value = null
  }
}
</script>

<style scoped>
/* Varias ventas: cada una en su recuadro. */
.venta-registrada {
  padding: 0.75rem;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.5rem;
}
</style>
