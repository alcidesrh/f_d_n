/**
 * Estado del calendario de salidas: el filtro (el primer día, cuántos días,
 * la empresa y una hora exacta), cómo se muestra lo ocupado, las salidas de
 * la ventana, la ruta resaltada (la fijada en la leyenda filtra «Todas»), las
 * salidas seleccionadas (para eliminarlas) y el reloj del «ahora». Se recuerda por usuario el modo de
 * ocupación; los días vuelven a uno (hoy) en cada visita. La lógica pura está
 * en `core/salida/calendario`.
 */
import { defineStore } from 'pinia'
import { computed, ref, shallowRef, watch } from 'vue'
import { fetchCalendario, fetchOpciones } from '@/core/salida/api'
import { aQueryCalendario, ESTADOS_CALENDARIO, desplazar, horaMinuto, leyenda, matriz, type ModoOcupacion, type Ventana, ventana } from '@/core/salida/calendario'
import { aDia } from '@/core/salida/filtro'
import type { Calendario, OpcionesSalidas } from '@/core/salida/types'

/** Cada cuánto se refrescan las salidas (ocupación, estados) mientras la pantalla está abierta. */
const REFRESCO_MS = 60_000

const mensaje = (e: unknown) => (e instanceof Error ? e.message : String(e))

export const useCalendarioSalidasStore = defineStore(
  'calendario-salidas',
  () => {
    const fecha = ref(aDia(new Date()))
    /** Solo las salidas a esta hora exacta (`HH:MM`), en todos los días; null = todas. */
    const hora = ref<string | null>(null)
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
    /** Las recibidas, a la hora filtrada si la hay. */
    const items = computed(() => {
      const todas = datos.value?.items ?? []
      return hora.value ? todas.filter((s) => horaMinuto(s.fecha) === hora.value) : todas
    })
    const celdas = computed(() => matriz(items.value, columnas.value))
    const rutas = computed(() => leyenda(items.value, datos.value?.rutas ?? []))
    const rutaPorId = computed(() => new Map((datos.value?.rutas ?? []).map((r) => [r.id, r])))
    const empresas = computed(() => new Map((opciones.value?.empresas ?? []).map((e) => [e.id, e.nombre])))
    const clases = computed(() => new Map((opciones.value?.clases ?? []).map((c) => [c.id, c.nombre])))
    const busPorId = computed(() => new Map((opciones.value?.buses ?? []).map((b) => [b.id, b])))
    const croquisPorId = computed(() => new Map((opciones.value?.croquis ?? []).map((c) => [c.id, c])))
    /** Las salidas que se ven en la grilla (las de los días de la ventana). */
    const visibles = computed(() => [...celdas.value.values()].flat(2))
    /** Lo que marca «Todas»: las visibles y, con una ruta fijada en la leyenda, solo las de esa ruta. */
    const seleccionables = computed(() => (fijada.value === null ? visibles.value : visibles.value.filter((s) => s.rutaId === fijada.value)))

    // Lo seleccionado que deja de verse (otra ventana u hora, eliminado) deja de estarlo: no se borra lo que no se ve.
    watch(visibles, (v) => {
      const ids = new Set(v.map((s) => s.id))
      if ([...seleccion.value].some((id) => !ids.has(id))) seleccion.value = new Set([...seleccion.value].filter((id) => ids.has(id)))
    })

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
    /** Vuelve al día actual. */
    const hoy = () => irA(aDia(new Date()))
    /** Pasa a los `dias` anteriores (-1) o siguientes (1): la fecha se mueve otro tanto. */
    const mover = (sentido: -1 | 1) => irA(desplazar(fecha.value, sentido * dias.value))

    function alternarSeleccion(id: number) {
      const s = new Set(seleccion.value)
      if (!s.delete(id)) s.add(id)
      seleccion.value = s
    }
    const todasMarcadas = computed(() => seleccionando.value && seleccionables.value.length > 0 && seleccionables.value.every((s) => seleccion.value.has(s.id)))
    /** Marca las que cumplen el filtro (`seleccionables`), o las desmarca si ya lo estaban. */
    function seleccionarTodas() {
      const ids = seleccionables.value.map((s) => s.id)
      const s = new Set(seleccion.value)
      if (todasMarcadas.value) ids.forEach((id) => s.delete(id))
      else ids.forEach((id) => s.add(id))
      seleccionando.value = true
      seleccion.value = s
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
      seleccionables,
      todasMarcadas,
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
