/**
 * Estado del calendario de salidas: el filtro (la fecha del centro, cuántos
 * días, estados, empresa, ruta y bus), cómo se muestra lo ocupado, las
 * salidas de la ventana, la ruta resaltada y el reloj del «ahora». Se
 * recuerdan por usuario los días visibles, los estados y el modo de
 * ocupación. La lógica pura está en `core/salida/calendario`.
 */
import { defineStore } from 'pinia'
import { computed, ref, shallowRef } from 'vue'
import { fetchCalendario, fetchOpciones } from '@/core/salida/api'
import { aQueryCalendario, ESTADOS_CALENDARIO, desplazar, leyenda, matriz, type ModoOcupacion, type Ventana, ventana } from '@/core/salida/calendario'
import { aDia } from '@/core/salida/filtro'
import type { Calendario, EstadoSalida, OpcionesSalidas } from '@/core/salida/types'

/** Cada cuánto se refrescan las salidas (ocupación, estados) mientras la pantalla está abierta. */
const REFRESCO_MS = 60_000

const mensaje = (e: unknown) => (e instanceof Error ? e.message : String(e))

export const useCalendarioSalidasStore = defineStore(
  'calendario-salidas',
  () => {
    const fecha = ref(aDia(new Date()))
    const dias = ref<Ventana>(7)
    const estados = ref<EstadoSalida[]>([...ESTADOS_CALENDARIO])
    const empresa = ref<number | null>(null)
    const ruta = ref<number | null>(null)
    const bus = ref<number | null>(null)
    const modo = ref<ModoOcupacion>('asientos')

    const opciones = shallowRef<OpcionesSalidas | null>(null)
    const datos = shallowRef<Calendario | null>(null)
    const cargando = ref(false)
    const error = ref('')
    /** Ruta resaltada al pasar sobre la leyenda: las demás salidas se atenúan. */
    const resaltada = ref<number | null>(null)
    /** Ruta fijada al pulsarla en la leyenda: su resaltado no cambia hasta que se vuelva a pulsar. */
    const fijada = ref<number | null>(null)
    /** Lo que se muestra: la ruta fijada manda sobre el paso del puntero. */
    const resaltadaEfectiva = computed(() => fijada.value ?? resaltada.value)
    const ahora = ref(new Date())

    const filtro = computed(() => ({ fecha: fecha.value, dias: dias.value, estados: estados.value, empresa: empresa.value, ruta: ruta.value, bus: bus.value }))
    const columnas = computed(() => ventana(fecha.value, dias.value))
    /** Las salidas recibidas son de la ventana pedida; si se cambió el filtro y aún no llega lo nuevo, se muestran igual. */
    const celdas = computed(() => matriz(datos.value?.items ?? [], columnas.value))
    const rutas = computed(() => leyenda(datos.value?.items ?? [], datos.value?.rutas ?? []))
    const rutaPorId = computed(() => new Map((datos.value?.rutas ?? []).map((r) => [r.id, r])))
    const empresas = computed(() => new Map((opciones.value?.empresas ?? []).map((e) => [e.id, e.nombre])))
    const clases = computed(() => new Map((opciones.value?.clases ?? []).map((c) => [c.id, c.nombre])))
    const busPorId = computed(() => new Map((opciones.value?.buses ?? []).map((b) => [b.id, b])))
    const croquisPorId = computed(() => new Map((opciones.value?.croquis ?? []).map((c) => [c.id, c])))
    /** Filtros que no son la fecha ni los días: para «Limpiar». */
    const filtrosActivos = computed(
      () => [empresa.value, ruta.value, bus.value].filter((v) => v !== null).length + (sonPorDefecto(estados.value) ? 0 : 1),
    )

    let pedido = 0
    async function cargar(silencioso = false) {
      const n = ++pedido
      if (!silencioso) cargando.value = true
      error.value = ''
      try {
        const d = await fetchCalendario(aQueryCalendario(filtro.value), silencioso)
        if (n === pedido) datos.value = d
      } catch (e) {
        if (n === pedido && !silencioso) error.value = mensaje(e)
      } finally {
        if (n === pedido) cargando.value = false
      }
    }

    async function cargarOpciones() {
      if (opciones.value) return
      try {
        opciones.value = await fetchOpciones()
      } catch {
        // Sin opciones el calendario funciona igual: solo faltan nombres de empresa y bus.
      }
    }

    function irA(dia: string) {
      fecha.value = dia
    }
    const hoy = () => irA(aDia(new Date()))
    /** Mueve el centro media ventana hacia atrás (-1) o adelante (1). */
    const mover = (sentido: -1 | 1) => irA(desplazar(fecha.value, sentido * Math.ceil(dias.value / 2)))

    function alternarEstado(e: EstadoSalida) {
      estados.value = estados.value.includes(e) ? estados.value.filter((x) => x !== e) : [...estados.value, e]
    }

    function limpiar() {
      empresa.value = null
      ruta.value = null
      bus.value = null
      estados.value = [...ESTADOS_CALENDARIO]
    }

    let reloj: ReturnType<typeof setInterval> | null = null
    let refresco: ReturnType<typeof setInterval> | null = null
    /** Mientras la pantalla está abierta: el ahora avanza cada 30 s y las salidas se refrescan cada minuto. */
    function activar() {
      desactivar()
      ahora.value = new Date()
      reloj = setInterval(() => (ahora.value = new Date()), 30_000)
      refresco = setInterval(() => document.visibilityState === 'visible' && void cargar(true), REFRESCO_MS)
    }
    function desactivar() {
      if (reloj) clearInterval(reloj)
      if (refresco) clearInterval(refresco)
      reloj = refresco = null
    }

    return {
      fecha,
      dias,
      estados,
      empresa,
      ruta,
      bus,
      modo,
      opciones,
      datos,
      cargando,
      error,
      resaltada,
      fijada,
      resaltadaEfectiva,
      ahora,
      filtro,
      columnas,
      celdas,
      rutas,
      rutaPorId,
      empresas,
      clases,
      busPorId,
      croquisPorId,
      filtrosActivos,
      cargar,
      cargarOpciones,
      irA,
      hoy,
      mover,
      alternarEstado,
      limpiar,
      activar,
      desactivar,
    }
  },
  { persist: { pick: ['dias', 'estados', 'modo'] } },
)

const sonPorDefecto = (e: EstadoSalida[]) => e.length === ESTADOS_CALENDARIO.length && ESTADOS_CALENDARIO.every((x) => e.includes(x))
