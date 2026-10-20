<!--
  "Consultar" del listado de boletos: viaje, pasajero, venta, factura, los
  demás boletos de la venta y las reasignaciones. Los boletos relacionados se
  abren aquí mismo (`ver`).
-->
<template>
  <Dialog :visible="visible" modal :header="`Boleto ${id ?? ''}`" class="w-[min(44rem,calc(100vw-1rem))]" @update:visible="emit('update:visible', $event)" @show="cargar">
    <Skeleton v-if="cargando" height="16rem" />
    <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
    <div v-else-if="b" class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center gap-2">
        <Tag :value="etiquetaEstado(b.estado).etiqueta" :severity="etiquetaEstado(b.estado).severidad" />
        <span class="text-sm text-muted-color">Asiento {{ b.asiento }} · clase {{ b.clase }}</span>
        <span class="ml-auto text-lg font-semibold tabular-nums">{{ b.precio?.texto ?? '—' }}<span v-if="b.sinCobro" class="ml-1 text-xs font-normal text-muted-color">(sin cobro)</span></span>
      </div>

      <section class="grid gap-x-6 gap-y-3 md:grid-cols-2">
        <Dato titulo="Viaje">
          <div class="font-medium">{{ b.sube }} → {{ b.baja }}</div>
          <div class="text-xs text-muted-color">{{ b.ruta }}</div>
        </Dato>
        <Dato titulo="Salida">
          <div class="font-medium">{{ fecha(b.salida.fecha) }} {{ hora(b.salida.fecha) }}</div>
          <div class="text-xs text-muted-color">#{{ b.salida.id }} · {{ b.salida.estado }}<template v-if="b.salida.bus"> · bus {{ b.salida.bus }}</template><template v-if="b.salida.empresa"> · {{ b.salida.empresa }}</template></div>
        </Dato>
        <Dato titulo="Pasajero">
          <div class="font-medium">{{ b.clienteBoleto?.nombre ?? '—' }}</div>
          <div v-if="b.clienteBoleto?.documento" class="text-xs text-muted-color">Doc. {{ b.clienteBoleto.documento }}</div>
        </Dato>
        <Dato titulo="Cliente del documento">
          <div class="font-medium">{{ b.clienteDocumento?.nombre ?? '—' }}</div>
          <div v-if="b.clienteDocumento" class="text-xs text-muted-color">NIT {{ b.clienteDocumento.documento }}<template v-if="b.clienteDocumento.email"> · {{ b.clienteDocumento.email }}</template></div>
        </Dato>
        <Dato titulo="Venta">
          <div class="font-medium">#{{ b.venta.id }} · {{ CANALES[b.venta.canal] ?? b.venta.canal }}<template v-if="b.venta.agencia"> ({{ b.venta.agencia }})</template></div>
          <div class="text-xs text-muted-color">
            {{ b.creada ? `${fecha(b.creada)} ${hora(b.creada)}` : 'Sin fecha' }}<template v-if="b.usuario"> · {{ b.usuario }}</template><template v-if="b.estacion"> · {{ b.estacion }}</template>
          </div>
          <div class="text-xs text-muted-color">{{ b.tipoPago ?? 'Sin tipo de pago' }} · total {{ b.venta.total?.texto ?? '—' }}</div>
        </Dato>
        <Dato titulo="Documento">
          <div class="font-medium">{{ documentoTexto(b.documento) }}</div>
          <template v-if="b.factura">
            <div v-if="b.factura.uuid" class="break-all text-xs text-muted-color">{{ b.factura.uuid }}</div>
            <div class="text-xs text-muted-color">Receptor {{ b.factura.receptorNit }} · {{ b.factura.receptorNombre }}</div>
            <div v-if="b.factura.anulada" class="text-xs text-red-600">Anulada {{ fecha(b.factura.anulada) }}<template v-if="b.factura.motivoAnulacion">: {{ b.factura.motivoAnulacion }}</template></div>
            <a v-if="b.factura.urlPdf" :href="b.factura.urlPdf" target="_blank" rel="noopener" class="text-xs">PDF del certificador</a>
          </template>
          <div v-if="b.venta.errorFacturacion" class="text-xs text-red-600">{{ b.venta.errorFacturacion }}</div>
        </Dato>
        <Dato v-if="b.referenciaExterna || b.autorizacion" titulo="Pago con tarjeta">
          <div v-if="b.autorizacion" class="text-sm">Autorización {{ b.autorizacion }}</div>
          <div v-if="b.referenciaExterna" class="text-xs text-muted-color">Referencia {{ b.referenciaExterna }}</div>
        </Dato>
        <Dato v-if="b.observacion" titulo="Observación">{{ b.observacion }}</Dato>
      </section>

      <section v-if="b.reasignadoDe || b.reasignadoA" class="flex flex-wrap gap-2 text-sm">
        <Button v-if="b.reasignadoDe" size="small" severity="secondary" outlined @click="emit('ver', b.reasignadoDe)"><icon name="undo" class="mr-1" />Reasignado del boleto {{ b.reasignadoDe }}</Button>
        <Button v-if="b.reasignadoA" size="small" severity="secondary" outlined @click="emit('ver', b.reasignadoA)"><icon name="redo" class="mr-1" />Reasignado al boleto {{ b.reasignadoA }}</Button>
      </section>

      <section v-if="b.otros.length" class="flex flex-col gap-1.5">
        <h3 class="m-0 text-xs font-medium uppercase tracking-wide text-muted-color">Otros boletos de la venta</h3>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="o in b.otros" :key="o.id" type="button" class="otro" @click="emit('ver', o.id)">
            <span class="font-medium">#{{ o.id }}</span> · asiento {{ o.asiento }} · <span class="text-muted-color">{{ etiquetaEstado(o.estado).etiqueta }}</span>
          </button>
        </div>
      </section>
    </div>
    <template #footer>
      <Button label="Bitácora" severity="secondary" text :disabled="!b" @click="emit('bitacora')"><template #icon><icon name="history" class="mr-1" /></template></Button>
      <Button label="Cerrar" @click="emit('update:visible', false)" />
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { defineComponent, h, ref, watch } from 'vue'
import { fetchDetalleBoleto } from '@/core/boleto/api'
import { documentoTexto, etiquetaEstado } from '@/core/boleto/listado'
import type { DetalleBoleto } from '@/core/boleto/types'
import { HttpError } from '@/core/http'
import { fecha, hora } from '@/core/venta/modelo'

const props = defineProps<{ visible: boolean; id: number | null }>()
const emit = defineEmits<{
  'update:visible': [visible: boolean]
  /** Abrir otro boleto (de la misma venta o de la reasignación). */
  ver: [id: number]
  bitacora: []
}>()

const CANALES: Record<string, string> = { estacion: 'Taquilla', agencia: 'Agencia', web: 'Página web' }

/** Un dato con su título. */
const Dato = defineComponent({
  props: { titulo: { type: String, required: true } },
  setup: (p, { slots }) => () => h('div', { class: 'min-w-0' }, [h('div', { class: 'text-xs font-medium uppercase tracking-wide text-muted-color' }, p.titulo), h('div', { class: 'mt-0.5 text-sm' }, slots.default?.())]),
})

const b = ref<DetalleBoleto | null>(null)
const cargando = ref(false)
const error = ref('')
let pedido = 0

async function cargar() {
  if (props.id === null) return
  const n = ++pedido
  cargando.value = true
  error.value = ''
  try {
    const d = await fetchDetalleBoleto(props.id)
    if (n === pedido) b.value = d
  } catch (e) {
    if (n === pedido) error.value = e instanceof HttpError ? e.message : 'No se pudo cargar el boleto.'
  } finally {
    if (n === pedido) cargando.value = false
  }
}

// Abrir otro boleto con el diálogo ya abierto.
watch(
  () => props.id,
  () => props.visible && void cargar(),
)
</script>

<style scoped>
.otro {
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-border-radius-md);
  background: transparent;
  color: inherit;
  font: inherit;
  font-size: 0.8125rem;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
}
.otro:hover {
  background: var(--p-content-hover-background);
}
</style>
