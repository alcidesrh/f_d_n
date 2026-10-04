/**
 * Búsqueda y elección de asientos en la página de inicio (ADR-023): origen,
 * destino, fecha (y regreso), las salidas de cada sentido y los asientos
 * elegidos en el croquis. La elección es local hasta "Pagar asientos": ahí
 * se aparta todo junto en el backend (`useCarrito().reservar`).
 *
 * Se guarda en `sessionStorage` para volver del pago (o recargar) sin
 * perder la elección.
 */
import { defineStore } from 'pinia'
import { computed, reactive, ref, watch } from 'vue'
import * as api from './api'
import { alternarAsiento, diaISO, sumaCentavos, tomadosPorOtros, type AsientoElegido } from './modelo'
import type { Catalogos, Salida, SalidaPublico, ViajePedido } from './tipos'

export type Sentido = 'ida' | 'regreso'
export const SENTIDOS: readonly Sentido[] = ['ida', 'regreso']

export interface Busqueda {
  origen: number | null
  destino: number | null
  /** `AAAA-MM-DD` */
  fecha: string | null
  idaVuelta: boolean
  regreso: string | null
}

export interface Eleccion {
  salida: Salida
  asientos: AsientoElegido[]
}

interface Lista {
  salidas: Salida[]
  cargando: boolean
  /** Código de error (traducible) o null. */
  error: { codigo: string; mensaje: string } | null
  /** Clave de la búsqueda que cargó la lista (descarta respuestas viejas). */
  clave: string
}

const CLAVE = 'fdn.viaje'

function leer(): { busqueda?: Busqueda; elegida?: Record<Sentido, Eleccion | null> } {
  try {
    return JSON.parse(sessionStorage.getItem(CLAVE) ?? '{}')
  } catch {
    return {}
  }
}

export const MAX_ASIENTOS_DEFECTO = 10

export const useViaje = defineStore('viaje', () => {
  const guardado = leer()
  const busqueda = reactive<Busqueda>({
    origen: null,
    destino: null,
    fecha: null,
    idaVuelta: false,
    regreso: null,
    ...guardado.busqueda,
  })
  const listas = reactive<Record<Sentido, Lista>>({
    ida: { salidas: [], cargando: false, error: null, clave: '' },
    regreso: { salidas: [], cargando: false, error: null, clave: '' },
  })
  const abierta = reactive<Record<Sentido, number | null>>({ ida: null, regreso: null })
  const elegida = reactive<Record<Sentido, Eleccion | null>>({ ida: null, regreso: null, ...guardado.elegida })
  const catalogos = ref<Catalogos | null>(null)
  /** Nombre de cada estación vista en el buscador (para los títulos de las listas). */
  const nombres = reactive<Record<number, string>>({})
  /** Sube cuando hay que recargar los croquis abiertos (p. ej. tras un 409). */
  const version = ref(0)

  const maxAsientos = computed(() => catalogos.value?.maxAsientos ?? MAX_ASIENTOS_DEFECTO)
  const sentidos = computed<Sentido[]>(() => (busqueda.idaVuelta ? ['ida', 'regreso'] : ['ida']))
  const completa = computed(() => !!(busqueda.origen && busqueda.destino && busqueda.fecha))

  const totalCentavos = computed(() => sentidos.value.reduce((t, s) => t + sumaCentavos(elegida[s]?.asientos ?? []), 0))
  const cantidad = computed(() => sentidos.value.reduce((n, s) => n + (elegida[s]?.asientos.length ?? 0), 0))
  /** Lo que falta para poder pagar: null si se puede. */
  const falta = computed<'ida' | 'regreso' | null>(() => {
    if (!elegida.ida?.asientos.length) return 'ida'
    if (busqueda.idaVuelta && !elegida.regreso?.asientos.length) return 'regreso'
    return null
  })

  watch(
    () => ({ busqueda: { ...busqueda }, elegida: { ida: elegida.ida, regreso: elegida.regreso } }),
    (v) => {
      try {
        sessionStorage.setItem(CLAVE, JSON.stringify(v))
      } catch {
        // sin almacenamiento: la elección dura lo que la pestaña
      }
    },
    { deep: true },
  )

  /** Parámetros de búsqueda de cada sentido (el regreso invierte origen y destino). */
  function parametros(s: Sentido): { origen: number; destino: number; fecha: string } | null {
    if (!busqueda.origen || !busqueda.destino) return null
    if (s === 'ida') return busqueda.fecha ? { origen: busqueda.origen, destino: busqueda.destino, fecha: busqueda.fecha } : null
    return busqueda.idaVuelta && busqueda.regreso ? { origen: busqueda.destino, destino: busqueda.origen, fecha: busqueda.regreso } : null
  }

  async function cargar(s: Sentido) {
    const p = parametros(s)
    const lista = listas[s]
    const clave = p ? `${p.origen}-${p.destino}-${p.fecha}` : ''
    lista.clave = clave
    if (!p) {
      lista.salidas = []
      lista.error = null
      abierta[s] = null
      return
    }
    lista.cargando = true
    lista.error = null
    try {
      const salidas = await api.salidas(p.origen, p.destino, p.fecha)
      if (lista.clave !== clave) return
      lista.salidas = salidas
      // La elección sigue si su salida sigue en la lista (con los datos al día).
      const e = elegida[s]
      const misma = e ? salidas.find((x) => x.id === e.salida.id && x.trayecto === e.salida.trayecto) : undefined
      elegida[s] = e && misma ? { ...e, salida: misma } : null
      if (abierta[s] !== null && !salidas.some((x) => x.id === abierta[s])) abierta[s] = null
    } catch (e) {
      if (lista.clave !== clave) return
      lista.salidas = []
      lista.error = e instanceof api.ErrorPublico ? { codigo: e.codigo, mensaje: e.message } : { codigo: 'general', mensaje: String(e) }
    } finally {
      if (lista.clave === clave) lista.cargando = false
    }
  }

  async function buscar() {
    if (!busqueda.idaVuelta) {
      busqueda.regreso = null
      elegida.regreso = null
    }
    await Promise.all(SENTIDOS.map(cargar))
  }

  function abrir(s: Sentido, salidaId: number) {
    abierta[s] = abierta[s] === salidaId ? null : salidaId
  }

  /** Toca un asiento libre. Elegir en otra salida del mismo sentido empieza de nuevo. Devuelve si se llegó al máximo. */
  function tocar(s: Sentido, salida: Salida, asiento: AsientoElegido): boolean {
    const actual = elegida[s]?.salida.id === salida.id ? elegida[s]!.asientos : []
    const r = alternarAsiento(actual, asiento, maxAsientos.value)
    elegida[s] = r.elegidos.length ? { salida, asientos: r.elegidos } : null
    return r.lleno
  }

  /** Quita de la elección lo que otro ocupó; devuelve los números quitados. */
  function depurar(s: Sentido, detalle: SalidaPublico): number[] {
    const e = elegida[s]
    if (!e || e.salida.id !== detalle.id) return []
    const tomados = tomadosPorOtros(e.asientos, detalle.ocupacion)
    if (!tomados.length) return []
    const restantes = e.asientos.filter((a) => !tomados.includes(a))
    elegida[s] = restantes.length ? { ...e, asientos: restantes } : null
    return tomados.map((a) => a.numero)
  }

  /** Tras un 409: quita los asientos que otro tomó y pide recargar los croquis. */
  function quitarConflictos(conflictos: Array<{ viaje: number; asientos: number[] }>) {
    for (const c of conflictos) {
      const s = sentidos.value[c.viaje]
      const e = s ? elegida[s] : null
      if (!s || !e) continue
      const restantes = e.asientos.filter((a) => !c.asientos.includes(a.id))
      elegida[s] = restantes.length ? { ...e, asientos: restantes } : null
    }
    version.value++
  }

  function pedido(): ViajePedido[] {
    return sentidos.value
      .map((s) => elegida[s])
      .filter((e): e is Eleccion => !!e && e.asientos.length > 0)
      .map((e) => ({ salida: e.salida.id, trayecto: e.salida.trayecto, asientos: e.asientos.map((a) => a.id) }))
  }

  function recordarNombres(estaciones: ReadonlyArray<{ id: number; nombre: string }>) {
    for (const e of estaciones) nombres[e.id] = e.nombre
  }

  function limpiar() {
    elegida.ida = null
    elegida.regreso = null
    abierta.ida = null
    abierta.regreso = null
  }

  /** Cancela la compra desde el inicio: búsqueda y elección vuelven a cero (la fecha, a hoy). */
  function cancelar() {
    Object.assign(busqueda, { origen: null, destino: null, fecha: diaISO(new Date()), idaVuelta: false, regreso: null })
    limpiar()
    for (const s of SENTIDOS) {
      listas[s].clave = ''
      listas[s].salidas = []
      listas[s].error = null
      listas[s].cargando = false
    }
  }

  let pidiendoCatalogos: Promise<Catalogos | null> | null = null
  /** Una sola petición aunque la pidan varios componentes a la vez. */
  async function cargarCatalogos() {
    if (catalogos.value) return catalogos.value
    pidiendoCatalogos ??= api.catalogos().catch(() => null)
    catalogos.value = await pidiendoCatalogos
    if (!catalogos.value) pidiendoCatalogos = null
    return catalogos.value
  }

  return {
    busqueda,
    listas,
    abierta,
    elegida,
    catalogos,
    nombres,
    version,
    maxAsientos,
    sentidos,
    completa,
    totalCentavos,
    cantidad,
    falta,
    buscar,
    cargar,
    abrir,
    tocar,
    depurar,
    quitarConflictos,
    pedido,
    limpiar,
    cancelar,
    recordarNombres,
    cargarCatalogos,
  }
})
