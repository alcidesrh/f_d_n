<!--
  Detalle de un asiento ocupado del croquis, solo para el personal (taquilla
  y "Ver salida"; la página pública no lo usa). Se abre con `abrir(evento,
  salida, asiento)` y agrupa lo que hay detrás del asiento: pasajero, cliente
  y venta (uno por tramo vendido, con los otros asientos de la misma compra;
  los de otra salida enlazan a la venta con ese boleto abierto), o la reserva
  web vigente.
  Al SUPER_ADMIN no se le muestra el pasajero (sí el cliente); solo él ve los
  datos de quién vendió (fecha, vendedor, estación) y la bitácora del boleto.
-->
<template>
  <Popover ref="popover" class="aop" @hide="detalle = null">
    <div class="flex w-[min(21rem,calc(100vw-2rem))] flex-col gap-3">
      <Skeleton v-if="cargando" height="9rem" />
      <Message v-else-if="error" severity="warn" :closable="false">{{ error }}</Message>
      <template v-else-if="detalle">
        <header class="flex items-center justify-between gap-2">
          <span class="font-semibold"
            >Asiento {{ detalle.asiento.numero }} · clase {{ detalle.asiento.clase }}</span
          >
          <Tag v-if="detalle.reserva" severity="warn" value="Reservado" />
        </header>

        <section v-if="detalle.reserva" class="aop-grupo">
          <h4>Reserva en la página</h4>
          <dl>
            <dt>Tramo</dt>
            <dd>{{ tramo(detalle.reserva.trayecto) }}</dd>
            <dt>Apartado</dt>
            <dd>{{ fechaHora(detalle.reserva.creada) }}</dd>
            <dt>Vence</dt>
            <dd>{{ fechaHora(detalle.reserva.expiraEn) }}</dd>
          </dl>
        </section>

        <article
          v-for="b in detalle.boletos"
          :key="b.id"
          class="flex flex-col gap-2"
          :class="{ 'aop-tramo': detalle.boletos.length > 1 }"
        >
          <section v-if="b.pasajero && !detalle.auditoria" class="aop-grupo">
            <h4>Pasajero</h4>
            <dl>
              <dt>Nombre</dt>
              <dd>{{ b.pasajero.nombre }}</dd>
              <template v-if="b.pasajero.documento"
                ><dt>{{ b.pasajero.tipoDocumento ?? 'Documento' }}</dt>
                <dd>{{ b.pasajero.documento }}</dd></template
              >
              <template v-if="b.pasajero.nacionalidad"
                ><dt>Nacionalidad</dt>
                <dd>{{ b.pasajero.nacionalidad }}</dd></template
              >
              <template v-if="b.pasajero.telefono"
                ><dt>Teléfono</dt>
                <dd>{{ b.pasajero.telefono }}</dd></template
              >
              <template v-if="b.pasajero.email"
                ><dt>Correo</dt>
                <dd class="break-all">{{ b.pasajero.email }}</dd></template
              >
            </dl>
          </section>

          <template v-if="b.completo">
            <section v-if="b.cliente" class="aop-grupo">
              <h4>Cliente</h4>
              <dl>
                <dt>Nombre</dt>
                <dd>{{ b.cliente.nombre }}</dd>
                <template v-if="b.cliente.email"
                  ><dt>Correo</dt>
                  <dd class="break-all">{{ b.cliente.email }}</dd></template
                >
                <template v-if="b.cliente.nit"
                  ><dt>NIT</dt>
                  <dd>{{ b.cliente.nit }}</dd></template
                >
                <template v-if="b.observacion"
                  ><dt>Observación</dt>
                  <dd>{{ b.observacion }}</dd></template
                >
              </dl>
            </section>

            <section class="aop-grupo">
              <h4>Venta<template v-if="detalle.auditoria"> #{{ b.venta.id }}</template></h4>
              <dl>
                <template v-if="detalle.auditoria">
                  <template v-if="b.venta.creada"
                    ><dt>Fecha</dt>
                    <dd>{{ fechaHora(b.venta.creada) }}</dd></template
                  >
                  <template v-if="b.venta.vendedor"
                    ><dt>Vendedor</dt>
                    <dd>{{ b.venta.vendedor }}</dd></template
                  >
                  <dt>Estación</dt>
                  <dd>{{ b.venta.estacion ?? '—' }}</dd>
                  <template v-if="b.venta.agencia"
                    ><dt>Agencia</dt>
                    <dd>{{ b.venta.agencia }}</dd></template
                  >
                  <template v-if="b.venta.comprador && b.venta.comprador !== b.pasajero?.nombre"
                    ><dt>Comprador</dt>
                    <dd>{{ b.venta.comprador }}</dd></template
                  >
                </template>
                <dt>Sube</dt>
                <dd>{{ b.trayecto.origen }}</dd>
                <dt>Baja</dt>
                <dd>{{ b.trayecto.destino }}</dd>
                <dt>Precio</dt>
                <dd class="tabular-nums">
                  {{ sinCobro(b) ? etiquetaCanal(b) : (b.precio?.texto ?? '—') }}
                </dd>
                <template v-if="b.relacionados?.length"
                  ><dt>Asientos relacionados</dt>
                  <dd class="aop-relacionados">
                    <span v-for="r in b.relacionados" :key="r.id" class="aop-relacionado">
                      <span class="font-mono font-semibold">{{ r.numero ?? '—' }}</span>
                      <RouterLink
                        v-if="r.otraSalida && enlaceDe(r)"
                        :to="enlaceDe(r)!"
                        class="aop-enlace"
                        :aria-label="`Ver el asiento ${r.numero} en su salida (${r.fecha})`"
                        v-tooltip.top="`Otra salida (${r.fecha}): ver el boleto`"
                        @click="popover?.hide()"
                        ><icon name="link" size="0.9rem"
                      /></RouterLink>
                    </span>
                  </dd></template
                >
              </dl>
            </section>
          </template>
          <p v-else class="m-0 text-sm text-muted-color">
            Vendido por otro usuario: no tiene acceso al detalle de esa venta.
          </p>

          <div
            v-if="detalle.auditoria || b.completo"
            class="aop-speed flex items-center justify-start"
          >
            <SpeedDial
              :model="accionesDe(b)"
              direction="right"
              type="linear"
              :transition-delay="60"
              :tooltip-options="{ event: 'hover', position: 'top' }"
              :button-props="{
                size: 'small',
                severity: 'secondary',
                outlined: true,
                'aria-label': 'Más acciones del boleto',
              }"
            >
              <template #icon><icon name="more-horiz" /></template>
              <template #itemicon="{ item }"><icon :name="String(item.icon)" /></template>
            </SpeedDial>
          </div>
        </article>
      </template>
    </div>
  </Popover>
  <!-- Fuera del popover: al abrirse el diálogo el popover se cierra. -->
  <BitacoraDialog
    v-if="bitacoraId !== null"
    :visible="true"
    tipo="boleto"
    :id="bitacoraId"
    @update:visible="(v: boolean) => !v && (bitacoraId = null)"
  />
  <EnviarPorChatDialog
    :visible="!!chatDe"
    :referencias="chatDe ? [{ tipo: 'BoletoAsiento', id: chatDe.id }] : []"
    @update:visible="(v: boolean) => !v && (chatDe = null)"
  />
  <CancelarBoletoDialog
    :visible="!!cancelarDe"
    :boleto="cancelarDe"
    @update:visible="(v: boolean) => !v && (cancelarDe = null)"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { MenuItem } from 'primevue/menuitem'
import { notify } from '@/core/notify'
import { fetchDetalleAsiento } from '@/core/venta/api'
import { usePermisosBoleto } from '@/core/venta/permisos'
import BitacoraDialog from '@/shared/bitacora/BitacoraDialog.vue'
import CancelarBoletoDialog from '@/shared/boleto/CancelarBoletoDialog.vue'
import { reimprimirBoletos } from '@/shared/boleto/reimprimir'
import EnviarPorChatDialog from '@/shared/chat/EnviarPorChatDialog.vue'
import type { BoletoACancelar } from '@/core/boleto/types'
import type { RouteLocationRaw } from 'vue-router'
import type {
  AsientoRelacionado,
  BoletoDeAsiento,
  DetalleAsiento,
  TrayectoAsiento,
} from '@/core/venta/types'

const emit = defineEmits<{
  /** Anular este boleto (el que muestra el detalle). */
  anular: [boleto: BoletoDeAsiento]
  /** Reasignar este boleto. */
  reasignar: [boleto: BoletoDeAsiento]
}>()

const permisos = usePermisosBoleto()
void permisos.cargar()

function elegir(accion: 'anular' | 'reasignar', boleto: BoletoDeAsiento) {
  popover.value?.hide()
  if (accion === 'anular') emit('anular', boleto)
  else emit('reasignar', boleto)
}

/** Boleto cuya bitácora se está viendo (solo SUPER_ADMIN). */
const bitacoraId = ref<number | null>(null)
function verBitacora(boleto: BoletoDeAsiento) {
  popover.value?.hide()
  bitacoraId.value = boleto.id
}

/** Boleto que se envía por el chat y boleto que se cancela (diálogos fuera del popover). */
const chatDe = ref<BoletoDeAsiento | null>(null)
const cancelarDe = ref<BoletoACancelar | null>(null)

/** Las acciones de cada boleto, en el desplegable: solo las que aplican. */
function accionesDe(b: BoletoDeAsiento): MenuItem[] {
  const items: MenuItem[] = []
  if (b.completo && b.operable && permisos.reasignar) {
    items.push({ label: 'Reasignar', icon: 'swap-horiz', command: () => elegir('reasignar', b) })
  }
  if (b.completo && b.operable && permisos.anular) {
    items.push({ label: 'Anular', icon: 'cancel-outline', command: () => elegir('anular', b) })
  }
  if (b.completo) {
    items.push({ label: 'Imp. factura', icon: 'print-outline', command: () => void imprimir(b) })
    items.push({ label: 'Chat', icon: 'forum-outline', command: () => abrirChat(b) })
  }
  if (b.completo && b.operable && permisos.cancelar) {
    items.push({ label: 'Cancelar', icon: 'person-off-outline', command: () => abrirCancelar(b) })
  }
  if (detalle.value?.auditoria) {
    items.push({ label: 'Bitácora', icon: 'history', command: () => verBitacora(b) })
  }
  return items
}

async function imprimir(b: BoletoDeAsiento) {
  try {
    await reimprimirBoletos([b.id])
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudo reimprimir.')
  }
}

function abrirChat(b: BoletoDeAsiento) {
  popover.value?.hide()
  chatDe.value = b
}

function abrirCancelar(b: BoletoDeAsiento) {
  if (!detalle.value) return
  popover.value?.hide()
  cancelarDe.value = {
    id: b.id,
    asiento: detalle.value.asiento.numero,
    precio: b.precio,
    sube: b.trayecto.origen,
    baja: b.trayecto.destino,
    clienteBoleto: b.pasajero ? { nombre: b.pasajero.nombre } : null,
  }
}

/** La venta con el boleto relacionado abierto: su día, su tramo, su salida y el detalle del asiento. */
function enlaceDe(r: AsientoRelacionado): RouteLocationRaw | null {
  if (r.salida == null || r.asiento == null || !r.fecha) return null
  return {
    name: 'venta',
    query: {
      fecha: r.fecha,
      salida: String(r.salida),
      asiento: String(r.asiento),
      ...(r.sube != null ? { sube: String(r.sube) } : {}),
      ...(r.baja != null ? { baja: String(r.baja) } : {}),
    },
  }
}

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
// Lo de agencia se cobró aunque el legado lo marque voucher (`voucher_agencia_id`).
const sinCobro = (b: BoletoDeAsiento) =>
  b.venta.cortesia || (b.venta.voucher && b.venta.canal !== 'agencia')

function etiquetaCanal(b: BoletoDeAsiento): string {
  if (b.venta.voucher && b.venta.canal !== 'agencia') return 'Voucher'
  if (b.venta.cortesia) return 'Cortesía'
  return (
    { estacion: 'Taquilla', agencia: 'Agencia', web: 'Página web' }[b.venta.canal] ?? b.venta.canal
  )
}

const fechaHora = (iso: string) =>
  new Intl.DateTimeFormat('es-GT', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(iso))
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
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--p-text-muted-color);
}
.aop-grupo dl {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  margin: 0 -0.35rem;
  font-size: 0.85rem;
}
/* Cada fila es un par dt/dd: se cuentan por tipo, así que el par n tiene el mismo índice en ambos. */
.aop-grupo dt,
.aop-grupo dd {
  padding: 0.15rem 0.35rem;
}
.aop-grupo dt {
  padding-right: 0.75rem;
  border-radius: 0.25rem 0 0 0.25rem;
  color: var(--p-text-muted-color);
}
.aop-grupo dd {
  min-width: 0;
  margin: 0;
  border-radius: 0 0.25rem 0.25rem 0;
  text-align: right;
  overflow-wrap: anywhere;
}
.aop-grupo dt:nth-of-type(even),
.aop-grupo dd:nth-of-type(even) {
  background: color-mix(in srgb, var(--p-surface-400) 10%, transparent);
}
.aop-relacionados {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.15rem 0.5rem;
}
.aop-relacionado {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
}
.aop-enlace {
  display: inline-flex;
  color: var(--p-primary-color);
}
.aop-enlace:hover {
  color: var(--p-primary-hover-color, var(--p-primary-color));
}
.aop-tramo + .aop-tramo {
  padding-top: 0.75rem;
  border-top: 1px dashed var(--p-content-border-color);
}
/*
  Las acciones del SpeedDial se abren a la derecha en su misma fila; con este espacio entre ellas
  caben las seis en un teléfono angosto. La clase va en el contenedor: SpeedDial tiene varias
  raíces y su `div` no recibe el `data-v` de este componente, así que el selector con scope no
  lo alcanzaría.
*/
.aop-speed :deep(.p-speeddial-list) {
  gap: 0.25rem;
}
</style>
