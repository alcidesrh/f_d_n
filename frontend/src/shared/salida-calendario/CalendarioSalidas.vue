<!--
  El calendario de salidas listo para una pantalla: los controles (fecha,
  días, hora, empresa), la leyenda de rutas, los avisos y la grilla (días en
  filas, horas en columnas). Lo usan las salidas (como grilla o solo como
  filtro del listado, `grilla=false`) y los boletos (pulsar una salida filtra
  sus boletos).

  Al montarse vuelve a hoy (o a `?fecha=AAAA-MM-DD`) con un día y la hora
  actual a la vista; cualquier cambio del filtro vuelve a pedir y la fecha
  elegida queda en la URL. Se refresca solo cada minuto. Pulsar una salida
  emite `abrir` (en modo selección, la marca); `eliminar` pide borrar las
  marcadas. El slot va al final de la fila de controles. `maxAlto` acota la
  grilla cuando hay algo debajo (si no, ocupa el resto de la ventana).
  Estado en `store.ts`; reglas en `core/salida/calendario`.
-->
<template>
  <div ref="raiz" class="flex flex-col gap-3">
    <div ref="cabecera" class="flex flex-col gap-3">
      <ControlesCalendario :ocupacion="grilla" :seleccion="grilla && seleccion" @hoy="irAHoy" @eliminar="emit('eliminar')"><slot /></ControlesCalendario>
      <LeyendaRutas />
      <Message v-if="store.error" severity="error" :closable="false">{{ store.error }}</Message>
      <Message v-if="grilla && store.datos?.truncado" severity="warn" :closable="false">Hay más salidas de las que se pueden mostrar: acote el filtro o elija menos días.</Message>
    </div>

    <GrillaCalendario v-if="grilla" ref="grillaRef" :activa="activa ?? null" :style="{ '--cal-arriba': `${arriba}px`, '--cal-max': maxAlto }" @abrir="(e, s) => emit('abrir', e, s)" />
  </div>
</template>

<script setup lang="ts">
import { aDia } from '@/core/salida/filtro'
import type { SalidaCalendario } from '@/core/salida/types'
import ControlesCalendario from './ControlesCalendario.vue'
import GrillaCalendario from './GrillaCalendario.vue'
import LeyendaRutas from './LeyendaRutas.vue'
import { useCalendarioSalidasStore } from './store'

const props = withDefaults(defineProps<{ grilla?: boolean; seleccion?: boolean; activa?: number | null; maxAlto?: string }>(), { grilla: true, seleccion: false, activa: null, maxAlto: undefined })
const emit = defineEmits<{ abrir: [evento: MouseEvent, salida: SalidaCalendario]; eliminar: [] }>()

const route = useRoute()
const router = useRouter()
const store = useCalendarioSalidasStore()

const grillaRef = ref<InstanceType<typeof GrillaCalendario> | null>(null)
const raiz = ref<HTMLElement | null>(null)
const cabecera = ref<HTMLElement | null>(null)

/** Distancia de la grilla al borde de arriba de la ventana: la grilla ocupa el resto del alto. */
const arriba = ref(240)
function medir() {
  const el = raiz.value?.querySelector<HTMLElement>('.cal')
  if (el) arriba.value = Math.max(0, Math.round(el.getBoundingClientRect().top + window.scrollY))
}

/** Vuelve a hoy (con los días elegidos) y a la hora actual a la vista. */
function irAHoy() {
  const mismoDia = store.fecha === aDia(new Date())
  store.hoy()
  if (mismoDia) grillaRef.value?.centrar({ suave: true })
}

async function cargarYCentrar() {
  await store.cargar()
  // Con los datos pintados (la leyenda cambia el alto de arriba) se mide, y con el alto nuevo aplicado se centra.
  await nextTick()
  medir()
  await nextTick()
  grillaRef.value?.centrar()
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
  () => listo && void nextTick(() => grillaRef.value?.centrar({ suave: true })),
)
watch(
  () => store.fecha,
  async (fecha) => {
    void router.replace({ query: { ...route.query, fecha: fecha === aDia(new Date()) ? undefined : fecha } })
    if (listo) await cargarYCentrar()
  },
)
// Al volver a mostrar la grilla (otra vista de la pantalla), se mide y la hora queda a la vista.
watch(
  () => props.grilla,
  async (v) => {
    if (!v) return
    await nextTick()
    medir()
    await nextTick()
    grillaRef.value?.centrar()
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

defineExpose({ cargar: () => store.cargar() })
</script>
