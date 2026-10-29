<!--
  Calendario de salidas: días en el eje X y horas del día en el Y; en cada
  cruce, las salidas de esa hora con el color de su ruta y lo ocupado (en
  asientos o, con el interruptor, en porciento). Al entrar, el momento actual
  queda en el centro; una fecha elegida (`?fecha=AAAA-MM-DD` o con los
  controles) pasa a ser el centro del eje X. Pulsar una salida abre su
  detalle; desde ahí, su croquis («Ver») y el chequeo. Se refresca solo cada
  minuto. Estado en `calendario/store.ts`; reglas en `core/salida/calendario`.
-->
<template>
  <div ref="raiz" class="@container flex flex-col gap-3">
    <nav class="flex flex-wrap justify-end gap-1" aria-label="Otras vistas de salidas">
      <RouterLink v-if="acceso.puede('salida.crear')" :to="{ name: 'salidas-programar' }" class="no-underline">
        <Button label="Programar salidas" severity="secondary" text size="small">
          <template #icon><icon name="calendar-add-on-outline" class="mr-1" /></template>
        </Button>
      </RouterLink>
      <RouterLink :to="{ name: 'salidas' }" class="no-underline">
        <Button label="Listado" severity="secondary" text size="small">
          <template #icon><icon name="list" class="mr-1" /></template>
        </Button>
      </RouterLink>
    </nav>

    <div ref="cabecera" class="flex flex-col gap-3">
      <ControlesCalendario @hoy="irAHoy" />
      <LeyendaRutas />
      <Message v-if="store.error" severity="error" :closable="false">{{ store.error }}</Message>
      <Message v-if="store.datos?.truncado" severity="warn" :closable="false">Hay más salidas de las que se pueden mostrar: acote el filtro o elija menos días.</Message>
    </div>

    <GrillaCalendario ref="grilla" :activa="activa?.id ?? null" :style="{ '--cal-arriba': `${arriba}px` }" @abrir="abrir" />

    <SalidaPopover ref="popover" @ver="(s) => (verSalida = s)" @cerrado="activa = null" />
    <VerSalidaDialog :salida="verSalida" @cerrar="verSalida = null" />
  </div>
</template>

<script setup lang="ts">
import { useAccesoStore } from '@/core/acceso/store'
import { aDia } from '@/core/salida/filtro'
import type { SalidaCalendario } from '@/core/salida/types'
import ControlesCalendario from './calendario/ControlesCalendario.vue'
import GrillaCalendario from './calendario/GrillaCalendario.vue'
import LeyendaRutas from './calendario/LeyendaRutas.vue'
import SalidaPopover from './calendario/SalidaPopover.vue'
import { useCalendarioSalidasStore } from './calendario/store'
import VerSalidaDialog from './VerSalidaDialog.vue'

const route = useRoute()
const router = useRouter()
const store = useCalendarioSalidasStore()
const acceso = useAccesoStore()

const grilla = ref<InstanceType<typeof GrillaCalendario> | null>(null)
const popover = ref<InstanceType<typeof SalidaPopover> | null>(null)
const raiz = ref<HTMLElement | null>(null)
const cabecera = ref<HTMLElement | null>(null)
const activa = shallowRef<SalidaCalendario | null>(null)
const verSalida = shallowRef<SalidaCalendario | null>(null)

/** Distancia de la grilla al borde de arriba de la ventana: la grilla ocupa el resto del alto. */
const arriba = ref(240)
function medir() {
  const el = raiz.value?.querySelector<HTMLElement>('.cal')
  if (el) arriba.value = Math.max(0, Math.round(el.getBoundingClientRect().top + window.scrollY))
}

function abrir(evento: MouseEvent, s: SalidaCalendario) {
  activa.value = s
  void popover.value?.abrir(evento, s)
}

/** Vuelve al momento actual: hoy al centro del eje X y la hora al centro del Y. */
function irAHoy() {
  const hoy = aDia(new Date())
  if (store.fecha === hoy) grilla.value?.centrar({ vertical: true, suave: true })
  else {
    centrarVertical = true
    store.irA(hoy)
  }
}

async function cargarYCentrar(vertical: boolean) {
  await store.cargar()
  // Con los datos pintados (la leyenda cambia el alto de arriba) se mide, y con el alto nuevo aplicado se centra.
  await nextTick()
  medir()
  await nextTick()
  grilla.value?.centrar({ vertical })
}

/** Hasta la primera carga los cambios del filtro no piden nada (la hace `onMounted`). */
let listo = false
/** El próximo cambio de fecha centra también el ahora en el eje Y («Hoy»). */
let centrarVertical = false

// Cualquier cambio del filtro vuelve a pedir; uno de fecha o de días, además, lleva la fecha al centro.
watch(
  () => JSON.stringify({ ...store.filtro, fecha: undefined, dias: undefined }),
  () => listo && void store.cargar(),
)
watch(
  () => store.dias,
  () => listo && void cargarYCentrar(false),
)
watch(
  () => store.fecha,
  async (fecha) => {
    void router.replace({ query: { ...route.query, fecha: fecha === aDia(new Date()) ? undefined : fecha } })
    if (!listo) return
    const vertical = centrarVertical
    centrarVertical = false
    await cargarYCentrar(vertical)
  },
)

const fechaPedida = () => {
  const f = String(route.query.fecha ?? '')
  return /^\d{4}-\d{2}-\d{2}$/.test(f) ? f : null
}

/** Lo de arriba de la grilla cambia de alto (filtros abiertos, leyenda): la grilla se ajusta. */
let observador: ResizeObserver | null = null

onMounted(async () => {
  window.addEventListener('resize', medir)
  observador = new ResizeObserver(() => medir())
  if (cabecera.value) observador.observe(cabecera.value)
  store.activar()
  // Las opciones (empresas) cambian el alto de los controles: antes de centrar.
  await store.cargarOpciones()
  const pedida = fechaPedida()
  // Sin `?fecha=`, el momento actual al centro (aunque la última visita haya quedado en otra fecha).
  store.irA(pedida ?? aDia(new Date()))
  await cargarYCentrar(true)
  listo = true
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', medir)
  observador?.disconnect()
  store.desactivar()
})
</script>
