<!--
  Calendario de salidas: un día por fila, de la fecha elegida en adelante
  (por defecto solo hoy), y las horas del día en columnas; en cada cruce, las
  salidas de esa hora con el color de su ruta y lo ocupado (en asientos o,
  con el interruptor, en porciento). Al entrar, la hora actual queda a la
  vista; una fecha elegida (`?fecha=AAAA-MM-DD` o con los controles) pasa a
  ser la primera fila. Pulsar una salida abre su detalle (su croquis y el
  chequeo) o, en modo selección, la marca: las marcadas se eliminan juntas
  (las que tienen boletos se avisan y quedan). Se refresca solo cada minuto.
  Estado en `calendario/store.ts`; reglas en `core/salida/calendario`.
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
      <ControlesCalendario @hoy="irAHoy" @eliminar="confirmarEliminar" />
      <LeyendaRutas />
      <Message v-if="store.error" severity="error" :closable="false">{{ store.error }}</Message>
      <Message v-if="store.datos?.truncado" severity="warn" :closable="false">Hay más salidas de las que se pueden mostrar: acote el filtro o elija menos días.</Message>
    </div>

    <GrillaCalendario ref="grilla" :activa="activa?.id ?? null" :style="{ '--cal-arriba': `${arriba}px` }" @abrir="abrir" />

    <SalidaPopover ref="popover" @ver="(s) => (verSalida = s)" @cerrado="activa = null" />
    <VerSalidaDialog :salida="verSalida" @cerrar="verSalida = null" />
    <ResultadoOperacionDialog :resultado="resultado" operacion="eliminar" @cerrar="resultado = null" />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useAccesoStore } from '@/core/acceso/store'
import { notify } from '@/core/notify'
import { eliminarSalidas } from '@/core/salida/api'
import { aDia } from '@/core/salida/filtro'
import { resumen } from '@/core/salida/resultado'
import type { ResultadoOperacion, SalidaCalendario } from '@/core/salida/types'
import ControlesCalendario from './calendario/ControlesCalendario.vue'
import GrillaCalendario from './calendario/GrillaCalendario.vue'
import LeyendaRutas from './calendario/LeyendaRutas.vue'
import SalidaPopover from './calendario/SalidaPopover.vue'
import { useCalendarioSalidasStore } from './calendario/store'
import ResultadoOperacionDialog from './ResultadoOperacionDialog.vue'
import VerSalidaDialog from './VerSalidaDialog.vue'

const route = useRoute()
const router = useRouter()
const store = useCalendarioSalidasStore()
const acceso = useAccesoStore()
const confirm = useConfirm()

const grilla = ref<InstanceType<typeof GrillaCalendario> | null>(null)
const popover = ref<InstanceType<typeof SalidaPopover> | null>(null)
const raiz = ref<HTMLElement | null>(null)
const cabecera = ref<HTMLElement | null>(null)
const activa = shallowRef<SalidaCalendario | null>(null)
const verSalida = shallowRef<SalidaCalendario | null>(null)
const resultado = shallowRef<ResultadoOperacion | null>(null)

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

/** Vuelve al momento actual: hoy (y los días elegidos) con la hora actual a la vista. */
function irAHoy() {
  const hoy = aDia(new Date())
  const mismoDia = store.fecha === hoy
  store.hoy()
  if (mismoDia) grilla.value?.centrar({ suave: true })
}

async function cargarYCentrar() {
  await store.cargar()
  // Con los datos pintados (la leyenda cambia el alto de arriba) se mide, y con el alto nuevo aplicado se centra.
  await nextTick()
  medir()
  await nextTick()
  grilla.value?.centrar()
}

/** Pide confirmación y elimina las seleccionadas; las que no se pudieron (con boletos, ya salieron) se listan. */
function confirmarEliminar() {
  const ids = [...store.seleccion]
  if (!ids.length) return
  confirm.require({
    header: 'Eliminar salidas',
    message: `${ids.length === 1 ? 'Se eliminará 1 salida' : `Se eliminarán ${ids.length} salidas`}. Las que tengan boletos o asientos apartados no se eliminan. No se puede deshacer. ¿Continuar?`,
    acceptProps: { label: 'Eliminar', severity: 'danger' },
    rejectProps: { label: 'Volver', severity: 'secondary', outlined: true },
    accept: () => void eliminar(ids),
  })
}

async function eliminar(ids: number[]) {
  try {
    const r = await eliminarSalidas(ids)
    const { texto, severidad } = resumen(r, 'eliminar')
    notify[severidad](texto)
    if (r.omitidas.length) resultado.value = r
    store.salirDeSeleccion()
  } catch (e) {
    notify.error(e instanceof Error ? e.message : 'No se pudieron eliminar las salidas.')
  }
  await store.cargar()
}

/** Hasta la primera carga los cambios del filtro no piden nada (la hace `onMounted`). */
let listo = false

// Cualquier cambio del filtro vuelve a pedir; uno de fecha o de días, además, lleva la hora a la vista.
watch(
  () => JSON.stringify({ ...store.filtro, fecha: undefined, dias: undefined }),
  () => listo && void store.cargar(),
)
watch(
  () => store.dias,
  () => listo && void cargarYCentrar(),
)
watch(
  () => store.hora,
  () => listo && void nextTick(() => grilla.value?.centrar({ suave: true })),
)
watch(
  () => store.fecha,
  async (fecha) => {
    void router.replace({ query: { ...route.query, fecha: fecha === aDia(new Date()) ? undefined : fecha } })
    if (listo) await cargarYCentrar()
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
  // Sin `?fecha=`, hoy y la hora actual (aunque la última visita haya quedado en otra fecha).
  store.hoy()
  store.dias = 1
  if (pedida) store.irA(pedida)
  await cargarYCentrar()
  listo = true
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', medir)
  observador?.disconnect()
  store.salirDeSeleccion()
  store.desactivar()
})
</script>
