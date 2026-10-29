<!--
  Detalle de una salida del calendario, como el de un asiento del croquis:
  se abre con `abrir(evento, salida)` junto a la ficha y agrupa el viaje
  (sale, llega, paradas), el bus (empresa, clase, distribución) y la
  ocupación (con lo vendido por canal y lo cobrado, que se pide al abrir).
  Acciones: ver el croquis (`ver`) y el chequeo de la salida.
-->
<template>
  <Popover ref="popover" @hide="cerrar">
    <div v-if="s" class="flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-3" :style="{ '--tono': tonoRuta(s.rutaId) }">
      <header class="flex items-start justify-between gap-2">
        <div class="flex min-w-0 items-start gap-2">
          <span class="sp-color" aria-hidden="true" />
          <div class="min-w-0">
            <div class="font-semibold leading-tight">{{ ruta?.nombre ?? 'Ruta' }}</div>
            <div class="text-xs text-muted-color">{{ ruta ? puntas(ruta) : '' }} · salida #{{ s.id }}</div>
          </div>
        </div>
        <div class="flex flex-none flex-col items-end gap-1">
          <Tag :value="etiquetaEstado(s.estado).etiqueta" :severity="etiquetaEstado(s.estado).severidad" />
          <Tag v-if="s.atrasada" value="Atrasada" severity="warn" class="!text-xs" />
        </div>
      </header>

      <section class="sp-grupo">
        <h4>Viaje</h4>
        <dl>
          <dt>Sale</dt>
          <dd class="first-letter:uppercase">{{ fechaHora(s.fecha) }}</dd>
          <template v-if="s.llegada"
            ><dt>Llega (aprox.)</dt><dd>{{ hora(s.llegada) }}<template v-if="otroDia(s.fecha, s.llegada)"> · {{ dia(s.llegada) }}</template></dd></template
          >
          <template v-if="ruta"><dt>Paradas</dt><dd>{{ ruta.paradas }}</dd></template>
        </dl>
      </section>

      <section class="sp-grupo">
        <h4>Bus</h4>
        <dl>
          <dt>Bus</dt>
          <dd>{{ bus ? [bus.codigo, bus.matricula].filter(Boolean).join(' · ') : 'Sin bus' }}</dd>
          <template v-if="s.empresaId"><dt>Empresa</dt><dd>{{ store.empresas.get(s.empresaId) ?? `#${s.empresaId}` }}</dd></template>
          <template v-if="bus?.claseId"><dt>Clase</dt><dd>{{ store.clases.get(bus.claseId) ?? '—' }}</dd></template>
          <template v-if="molde"><dt>Distribución</dt><dd>{{ etiquetaCroquis(molde) }}</dd></template>
        </dl>
      </section>

      <section class="sp-grupo">
        <h4>Ocupación</h4>
        <div class="sp-barra" role="meter" :aria-valuenow="Math.round((fraccion(s) ?? 0) * 100)" aria-valuemin="0" aria-valuemax="100" :aria-label="detalleOcupacion(s)">
          <span :style="{ width: `${(fraccion(s) ?? 0) * 100}%` }" />
        </div>
        <p class="m-0 text-sm">{{ detalleOcupacion(s) }}</p>
        <Skeleton v-if="cargando" height="3.5rem" />
        <dl v-else-if="resumen">
          <template v-for="c in canales" :key="c.etiqueta"
            ><template v-if="c.n"><dt>{{ c.etiqueta }}</dt><dd class="tabular-nums">{{ c.n }}</dd></template></template
          >
          <template v-if="resumen.reservados"><dt>Apartados en la página</dt><dd class="tabular-nums">{{ resumen.reservados }}</dd></template>
          <template v-if="resumen.ingresos"><dt>Cobrado</dt><dd class="tabular-nums">{{ resumen.ingresos.texto }}</dd></template>
        </dl>
      </section>

      <footer class="flex flex-wrap justify-end gap-2">
        <RouterLink v-if="puedeChequear" :to="{ name: 'salidas-chequeo', query: { salida: s.id } }" class="no-underline" @click="popover?.hide()">
          <Button label="Chequeo" size="small" severity="secondary" text>
            <template #icon><icon name="how-to-reg-outline" class="mr-1" /></template>
          </Button>
        </RouterLink>
        <Button v-if="puedeEmitir" label="Emitir boleto" size="small" @click="emitir">
          <template #icon><icon name="confirmation-number-outline" class="mr-1" /></template>
        </Button>
        <Button label="Ver croquis" size="small" severity="secondary" outlined @click="ver">
          <template #icon><icon name="airline-seat-recline-normal" class="mr-1" /></template>
        </Button>
      </footer>
    </div>
  </Popover>
</template>

<script setup lang="ts">
import { useAccesoStore } from '@/core/acceso/store'
import { etiquetaCroquis } from '@/core/salida/buses'
import { detalleOcupacion, fraccion, puntas, tonoRuta } from '@/core/salida/calendario'
import { aDia } from '@/core/salida/filtro'
import { fetchDetalle } from '@/core/salida/api'
import { etiquetaEstado } from '@/core/salida/filtro'
import type { ResumenSalida, SalidaCalendario } from '@/core/salida/types'
import { useCalendarioSalidasStore } from './store'

const emit = defineEmits<{
  /** Ver el croquis con la ocupación (el diálogo «Ver» de las salidas). */
  ver: [salida: SalidaCalendario]
  /** El popover se cerró. */
  cerrado: []
}>()

const store = useCalendarioSalidasStore()
const acceso = useAccesoStore()
const router = useRouter()
const popover = ref<InstanceType<typeof import('primevue/popover').default> | null>(null)
const s = shallowRef<SalidaCalendario | null>(null)
const resumen = shallowRef<ResumenSalida | null>(null)
const cargando = ref(false)
let pedido = 0

const ruta = computed(() => (s.value ? store.rutaPorId.get(s.value.rutaId) : undefined))
const bus = computed(() => (s.value?.busId ? store.busPorId.get(s.value.busId) : undefined))
const molde = computed(() => (bus.value?.croquisId ? store.croquisPorId.get(bus.value.croquisId) : undefined))
const puedeChequear = computed(
  () => !!s.value && ['programada', 'abordando', 'iniciada'].includes(s.value.estado) && acceso.puede(['boleto.chequear', 'salida.abordar', 'salida.iniciar', 'salida.finalizar']),
)
/** Emitir un boleto: solo en salidas que aún no partieron, con el permiso de vender. */
const puedeEmitir = computed(() => !!s.value && ['programada', 'abordando'].includes(s.value.estado) && acceso.puede('venta.vender'))
const canales = computed(() => {
  const c = resumen.value?.canales
  return c
    ? [
        { etiqueta: 'Taquilla', n: c.estacion },
        { etiqueta: 'Agencias', n: c.agencia },
        { etiqueta: 'Página web', n: c.web },
        { etiqueta: 'Cortesías', n: c.cortesia },
        { etiqueta: 'Vouchers', n: c.voucher },
      ]
    : []
})

async function abrir(evento: Event, salida: SalidaCalendario) {
  const mio = ++pedido
  // Otra ficha: se cierra y se vuelve a abrir junto a ella.
  if (s.value && s.value.id !== salida.id) popover.value?.hide()
  s.value = salida
  resumen.value = null
  await nextTick()
  popover.value?.show(evento)
  if (!salida.vendidos) return
  cargando.value = true
  try {
    const d = await fetchDetalle(salida.id)
    if (mio === pedido) resumen.value = d.resumen
  } catch {
    // El resumen es un extra: sin él quedan los números de la ficha.
  } finally {
    if (mio === pedido) cargando.value = false
  }
}

/** La venta abre con esta salida elegida (`/venta?salida=&fecha=`). */
function emitir() {
  const actual = s.value
  popover.value?.hide()
  if (actual) void router.push({ name: 'venta', query: { salida: actual.id, fecha: aDia(new Date(actual.fecha)) } })
}

function ver() {
  const actual = s.value
  popover.value?.hide()
  if (actual) emit('ver', actual)
}

function cerrar() {
  pedido++
  cargando.value = false
  emit('cerrado')
}

defineExpose({ abrir })

const fechaHora = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'long', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
const hora = (iso: string) => new Intl.DateTimeFormat('es-GT', { hour: 'numeric', minute: '2-digit' }).format(new Date(iso))
const dia = (iso: string) => new Intl.DateTimeFormat('es-GT', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(iso))
const otroDia = (a: string, b: string) => new Date(a).toDateString() !== new Date(b).toDateString()
</script>

<style scoped>
.sp-color {
  width: 0.85rem;
  height: 0.85rem;
  flex: none;
  margin-top: 0.2rem;
  border-radius: 0.2rem;
  background: light-dark(hsl(calc(var(--tono) * 1deg) 62% 48%), hsl(calc(var(--tono) * 1deg) 60% 62%));
}
.sp-grupo {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
}
.sp-grupo h4 {
  margin: 0;
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.sp-grupo dl {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.15rem 0.75rem;
  margin: 0;
  font-size: 0.85rem;
}
.sp-grupo dt {
  color: var(--p-text-muted-color);
}
.sp-grupo dd {
  min-width: 0;
  margin: 0;
  overflow-wrap: anywhere;
  text-align: right;
}
.sp-barra {
  height: 0.45rem;
  overflow: hidden;
  border-radius: 999px;
  background: light-dark(hsl(calc(var(--tono) * 1deg) 50% 92%), hsl(calc(var(--tono) * 1deg) 20% 22%));
}
.sp-barra > span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: light-dark(hsl(calc(var(--tono) * 1deg) 62% 48%), hsl(calc(var(--tono) * 1deg) 60% 62%));
}
</style>
