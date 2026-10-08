<!--
  Detalle de un asiento ocupado del croquis, solo para el personal (taquilla
  y "Ver salida"; la página pública no lo usa). Se abre con `abrir(evento,
  salida, asiento)` y agrupa lo que hay detrás del asiento: pasajero, viaje,
  venta, cobro y factura (uno por tramo vendido), o la reserva web vigente.
-->
<template>
  <Popover ref="popover" class="aop" @hide="detalle = null">
    <div class="flex w-[min(21rem,calc(100vw-2rem))] flex-col gap-3">
      <Skeleton v-if="cargando" height="9rem" />
      <Message v-else-if="error" severity="warn" :closable="false">{{ error }}</Message>
      <template v-else-if="detalle">
        <header class="flex items-center justify-between gap-2">
          <span class="font-semibold">Asiento {{ detalle.asiento.numero }} · clase {{ detalle.asiento.clase }}</span>
          <Tag v-if="detalle.reserva" severity="warn" value="Reservado" />
        </header>

        <section v-if="detalle.reserva" class="aop-grupo">
          <h4>Reserva en la página</h4>
          <dl>
            <dt>Tramo</dt><dd>{{ tramo(detalle.reserva.trayecto) }}</dd>
            <dt>Apartado</dt><dd>{{ fechaHora(detalle.reserva.creada) }}</dd>
            <dt>Vence</dt><dd>{{ fechaHora(detalle.reserva.expiraEn) }}</dd>
          </dl>
        </section>

        <article v-for="b in detalle.boletos" :key="b.id" class="flex flex-col gap-2" :class="{ 'aop-tramo': detalle.boletos.length > 1 }">
          <div class="flex flex-wrap items-center gap-1.5">
            <Tag severity="secondary" :value="etiquetaCanal(b)" />
            <Tag :severity="b.estado === 'emitido' ? 'info' : 'success'" :value="ESTADOS[b.estado] ?? b.estado" />
          </div>

          <section v-if="b.pasajero" class="aop-grupo">
            <h4>Pasajero</h4>
            <dl>
              <dt>Nombre</dt><dd>{{ b.pasajero.nombre }}</dd>
              <template v-if="b.pasajero.documento"><dt>{{ b.pasajero.tipoDocumento ?? 'Documento' }}</dt><dd>{{ b.pasajero.documento }}</dd></template>
              <template v-if="b.pasajero.nacionalidad"><dt>Nacionalidad</dt><dd>{{ b.pasajero.nacionalidad }}</dd></template>
              <template v-if="b.pasajero.telefono"><dt>Teléfono</dt><dd>{{ b.pasajero.telefono }}</dd></template>
              <template v-if="b.pasajero.email"><dt>Correo</dt><dd class="break-all">{{ b.pasajero.email }}</dd></template>
            </dl>
          </section>

          <section class="aop-grupo">
            <h4>Viaje</h4>
            <dl>
              <dt>Tramo</dt><dd>{{ tramo(b.trayecto) }}</dd>
              <template v-if="b.observacion"><dt>Nota</dt><dd>{{ b.observacion }}</dd></template>
            </dl>
          </section>

          <template v-if="b.completo">
            <section class="aop-grupo">
              <h4>Venta #{{ b.venta.id }}</h4>
              <dl>
                <template v-if="b.venta.creada"><dt>Fecha</dt><dd>{{ fechaHora(b.venta.creada) }}</dd></template>
                <template v-if="b.venta.vendedor"><dt>Vendedor</dt><dd>{{ b.venta.vendedor }}</dd></template>
                <template v-if="b.venta.estacion ?? b.venta.agencia"><dt>{{ b.venta.agencia ? 'Agencia' : 'Estación' }}</dt><dd>{{ b.venta.agencia ?? b.venta.estacion }}</dd></template>
                <template v-if="b.venta.comprador && b.venta.comprador !== b.pasajero?.nombre"><dt>Comprador</dt><dd>{{ b.venta.comprador }}</dd></template>
              </dl>
            </section>

            <section v-if="!sinCobro(b)" class="aop-grupo">
              <h4>Cobro</h4>
              <dl>
                <dt>Boleto</dt><dd class="tabular-nums">{{ b.precio?.texto ?? '—' }}</dd>
                <template v-if="b.venta.total && b.venta.total.centavos !== b.precio?.centavos"><dt>Total de la venta</dt><dd class="tabular-nums">{{ b.venta.total.texto }}</dd></template>
                <template v-if="b.venta.tipoPago"><dt>Forma de pago</dt><dd>{{ b.venta.tipoPago }}</dd></template>
                <template v-if="b.venta.referenciaPago"><dt>Autorización</dt><dd>{{ b.venta.referenciaPago }}</dd></template>
              </dl>
            </section>

            <section v-if="b.venta.factura" class="aop-grupo">
              <h4>Factura</h4>
              <dl>
                <dt>Número</dt><dd>{{ [b.venta.factura.serie, b.venta.factura.numero].filter(Boolean).join(' – ') || '—' }}</dd>
                <template v-if="b.venta.factura.nombre"><dt>A nombre de</dt><dd>{{ b.venta.factura.nombre }}</dd></template>
                <template v-if="b.venta.factura.nit"><dt>NIT</dt><dd>{{ b.venta.factura.nit }}</dd></template>
              </dl>
              <a v-if="b.venta.factura.urlPdf" :href="b.venta.factura.urlPdf" target="_blank" rel="noopener" class="text-sm underline">Ver factura</a>
            </section>
            <p v-else-if="b.venta.estadoFacturacion === 'pendiente'" class="m-0 text-sm text-muted-color">Factura pendiente de certificar.</p>
          </template>
          <p v-else class="m-0 text-sm text-muted-color">Vendido por otro usuario: no tiene acceso al detalle de esa venta.</p>
        </article>
      </template>
    </div>
  </Popover>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { fetchDetalleAsiento } from '@/core/venta/api'
import type { BoletoDeAsiento, DetalleAsiento, TrayectoAsiento } from '@/core/venta/types'

const ESTADOS: Record<string, string> = {
  emitido: 'Emitido',
  chequeado: 'Chequeado',
  transito: 'En tránsito',
  finalizado: 'Finalizado',
}

const popover = ref<InstanceType<typeof import('primevue/popover').default> | null>(null)
const detalle = ref<DetalleAsiento | null>(null)
const cargando = ref(false)
const error = ref('')
let pedido = 0

async function abrir(evento: Event, salida: number, asiento: number) {
  const mio = ++pedido
  popover.value?.hide()
  detalle.value = null
  error.value = ''
  cargando.value = true
  popover.value?.show(evento)
  try {
    const d = await fetchDetalleAsiento(salida, asiento)
    if (mio === pedido) detalle.value = d
  } catch {
    if (mio === pedido) error.value = 'No se pudo cargar el detalle del asiento.'
  } finally {
    if (mio === pedido) cargando.value = false
  }
}

defineExpose({ abrir })

const tramo = (t: TrayectoAsiento) => `${t.origen} → ${t.destino}`
const sinCobro = (b: BoletoDeAsiento) => b.venta.cortesia || b.venta.voucher

function etiquetaCanal(b: BoletoDeAsiento): string {
  if (b.venta.voucher) return 'Voucher'
  if (b.venta.cortesia) return 'Cortesía'
  return { estacion: 'Taquilla', agencia: 'Agencia', web: 'Página web' }[b.venta.canal] ?? b.venta.canal
}

const fechaHora = (iso: string) =>
  new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
</script>

<style scoped>
.aop-grupo {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}
.aop-grupo h4 {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
}
.aop-grupo dl {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.15rem 0.75rem;
  margin: 0;
  font-size: 0.85rem;
}
.aop-grupo dt {
  color: var(--p-text-muted-color);
}
.aop-grupo dd {
  min-width: 0;
  margin: 0;
  text-align: right;
  overflow-wrap: anywhere;
}
.aop-tramo + .aop-tramo {
  padding-top: 0.75rem;
  border-top: 1px dashed var(--p-content-border-color);
}
</style>
