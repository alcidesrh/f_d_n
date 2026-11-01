/**
 * Estado del calendario de salidas: el filtro (el primer día y la hora a la
 * vista, cuántos días y la empresa), cómo se muestra lo ocupado, las salidas
 * de la ventana, la ruta resaltada, las salidas seleccionadas (para
 * eliminarlas) y el reloj del «ahora». Se recuerda por usuario el modo de
 * ocupación; los días vuelven a uno (hoy) en cada visita. La lógica pura está
 * en `core/salida/calendario`.
 */
import { defineStore } from 'pinia'
import { computed, ref, shallowRef } from 'vue'
import { fetchCalendario, fetchOpciones } from '@/core/salida/api'
import { aQueryCalendario, ESTADOS_CALENDARIO, desplazar, leyenda, matriz, type ModoOcupacion, type Ventana, ventana } from '@/core/salida/calendario'
import { aDia } from '@/core/salida/filtro'
import type { Calendario, OpcionesSalidas } from '@/core/salida/types'

/** Cada cuánto se refrescan las salidas (ocupación, estados) mientras la pantalla está abierta. */
const REFRESCO_MS = 60_000

const mensaje = (e: unknown) => (e instanceof Error ? e.message : String(e))

export const useCalendarioSalidasStore = defineStore(
  'calendario-salidas',
  () => {
    const fecha = ref(aDia(new Date()))
    /** Hora a la vista (`HH:MM`): la grilla se desplaza hasta su columna. */
    const hora = ref(horaActual())
    const dias = ref<Ventana>(1)
    const empresa = ref<number | null>(null)
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

    /** Modo selección: pulsar una ficha la marca en vez de abrir su detalle. */
    const seleccionando = ref(false)
    const seleccion = ref<ReadonlySet<number>>(new Set())

    const filtro = computed(() => ({ fecha: fecha.value, dias: dias.value, estados: ESTADOS_CALENDARIO, empresa: empresa.value, ruta: null, bus: null }))
    const columnas = computed(() => ventana(fecha.value, dias.value))
    /** Las salidas recibidas son de la ventana pedida; si se cambió el filtro y aún no llega lo nuevo, se muestran igual. */
    const celdas = computed(() => matriz(datos.value?.items ?? [], columnas.value))
    const rutas = computed(() => leyenda(datos.value?.items ?? [], datos.value?.rutas ?? []))
    const rutaPorId = computed(() => new Map((datos.value?.rutas ?? []).map((r) => [r.id, r])))
    const empresas = computed(() => new Map((opciones.value?.empresas ?? []).map((e) => [e.id, e.nombre])))
    const clases = computed(() => new Map((opciones.value?.clases ?? []).map((c) => [c.id, c.nombre])))
    const busPorId = computed(() => new Map((opciones.value?.buses ?? []).map((b) => [b.id, b])))
    const croquisPorId = computed(() => new Map((opciones.value?.croquis ?? []).map((c) => [c.id, c])))
    /** Las salidas que se ven en la grilla (las de los días de la ventana). */
    const visibles = computed(() => [...celdas.value.values()].flat(2))

    let pedido = 0
    async function cargar(silencioso = false) {
      const n = ++pedido
      if (!silencioso) cargando.value = true
      error.value = ''
      try {
        const d = await fetchCalendario(aQueryCalendario(filtro.value), silencioso)
        if (n === pedido) {
          datos.value = d
          // Lo seleccionado que ya no se ve (otra ventana, eliminado) deja de estarlo.
          const ids = new Set(visibles.value.map((s) => s.id))
          if ([...seleccion.value].some((id) => !ids.has(id))) seleccion.value = new Set([...seleccion.value].filter((id) => ids.has(id)))
        }
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
    /** Vuelve al día y la hora actuales. */
    function hoy() {
      hora.value = horaActual()
      irA(aDia(new Date()))
    }
    /** Pasa a los `dias` anteriores (-1) o siguientes (1): la fecha se mueve otro tanto. */
    const mover = (sentido: -1 | 1) => irA(desplazar(fecha.value, sentido * dias.value))

    function alternarSeleccion(id: number) {
      const s = new Set(seleccion.value)
      if (!s.delete(id)) s.add(id)
      seleccion.value = s
    }
    /** Todas las salidas a la vista, o ninguna si ya lo estaban. */
    function seleccionarTodas() {
      seleccionando.value = true
      const ids = visibles.value.map((s) => s.id)
      seleccion.value = ids.length && ids.every((id) => seleccion.value.has(id)) ? new Set() : new Set(ids)
    }
    function salirDeSeleccion() {
      seleccionando.value = false
      seleccion.value = new Set()
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
      hora,
      dias,
      empresa,
      modo,
      seleccionando,
      seleccion,
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
      visibles,
      cargar,
      cargarOpciones,
      irA,
      hoy,
      mover,
      alternarSeleccion,
      seleccionarTodas,
      salirDeSeleccion,
      activar,
      desactivar,
    }
  },
  { persist: { pick: ['modo'] } },
)

function horaActual(d = new Date()): string {
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
