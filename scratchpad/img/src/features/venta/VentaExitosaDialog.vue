<!-- Venta registrada: resumen y reimpresión del ticket / PDF. -->
<template>
  <Dialog
    :visible="!!comprobante"
    modal
    :header="reasignacion ? 'Reasignación registrada' : 'Venta registrada'"
    :style="{ width: '30rem' }"
    @update:visible="!$event && emit('cerrar')"
  >
    <div v-if="comprobante" class="flex flex-col gap-3">
      <Message :severity="severidad" :closable="false">{{ mensaje }}</Message>
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt class="text-muted-color">Venta</dt>
        <dd class="font-mono">{{ comprobante.codigoBarras }}</dd>
        <template v-if="comprobante.factura">
          <dt class="text-muted-color">DTE</dt>
          <dd>{{ comprobante.factura.numero }} · serie {{ comprobante.factura.serie }}</dd>
        </template>
        <template v-if="comprobante.numeroAcceso">
          <dt class="text-muted-color">No. acceso</dt>
          <dd class="font-mono">{{ comprobante.numeroAcceso }}</dd>
        </template>
        <dt class="text-muted-color">Asientos</dt>
        <dd>{{ comprobante.boletos.map((b) => b.asiento).join(', ') }}</dd>
        <dt class="text-muted-color">Total</dt>
        <dd class="font-semibold">{{ comprobante.total.texto }}</dd>
      </dl>
    </div>
    <template #footer>
      <div class="flex flex-wrap justify-end gap-2">
        <a
          v-if="comprobante?.factura?.urlPdf"
          :href="comprobante.factura.urlPdf"
          target="_blank"
          rel="noopener"
          class="no-underline"
        >
          <Button label="Factura (DTE)" severity="secondary" text />
        </a>
        <Button label="PDF" severity="secondary" outlined :loading="descargando" @click="pdf">
          <template #icon><icon name="picture-as-pdf-outline" class="mr-1" /></template>
        </Button>
        <Button label="Imprimir ticket" severity="secondary" outlined @click="imprimir">
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
import { notify } from '@/core/notify'
import type { Comprobante } from '@/core/venta/types'
import { imprimirTicket } from '@/shared/boleto/ticket'

const props = defineProps<{ comprobante: Comprobante | null; reasignacion?: boolean }>()
const emit = defineEmits<{ cerrar: [] }>()
const descargando = ref(false)

const severidad = computed(() =>
  !props.reasignacion && props.comprobante?.estadoFacturacion === 'pendiente' ? 'warn' : 'success',
)
const mensaje = computed(() => {
  const c = props.comprobante
  if (!c) return ''
  if (props.reasignacion) return 'Boleto(s) reasignado(s). Imprima el ticket nuevo y entréguelo al pasajero.'
  if (c.cortesia) return 'Cortesía emitida.'
  if (c.estadoFacturacion === 'certificada') return 'Factura electrónica certificada.'
  if (c.estadoFacturacion === 'pendiente')
    return 'Venta en contingencia: la factura se certificará después.'
  return c.canal === 'agencia'
    ? 'Venta de agencia registrada (se descontó del saldo).'
    : 'Venta registrada.'
})

function imprimir() {
  if (props.comprobante) imprimirTicket(props.comprobante)
}

async function pdf() {
  if (!props.comprobante) return
  descargando.value = true
  try {
    const url = URL.createObjectURL(await fetchComprobantePdf(props.comprobante.id))
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch {
    notify.error('No se pudo generar el PDF.')
  } finally {
    descargando.value = false
  }
}
</script>
