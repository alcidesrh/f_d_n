/**
 * Cola de acciones de pantalla para la bitácora de actividad (familia
 * `pantalla`, nivel máximo): se juntan en memoria y salen en lote. Pura: el
 * reloj y el envío los pone `captura.ts`.
 */

export interface EventoPantalla {
  /** `navegar`, `imprimir`, `accion`… (hasta 60 caracteres). */
  accion: string
  /** Ruta de la SPA donde ocurrió (sin la consulta). */
  pantalla: string
  /** Milisegundos desde 1970 (hora del navegador). */
  fecha: number
  /** Lo que duró: en `navegar`, cuánto estuvo en la pantalla anterior. */
  duracionMs?: number
  detalle?: Record<string, unknown>
}

/** Lo que acepta el backend por lote (`ActividadController::MAXIMO_EVENTOS`). */
export const MAXIMO_POR_LOTE = 200
/** Si no se puede mandar (sin red), no se acumula sin fin: se descarta lo más viejo. */
export const MAXIMO_EN_COLA = 1000

export interface Cola {
  anotar(e: EventoPantalla): void
  /** Saca hasta `MAXIMO_POR_LOTE` eventos (los más viejos primero). */
  tomar(): EventoPantalla[]
  /** Devuelve al frente un lote que no se pudo mandar. */
  devolver(lote: EventoPantalla[]): void
  readonly tamano: number
}

export function crearCola(maximo = MAXIMO_EN_COLA): Cola {
  let eventos: EventoPantalla[] = []
  const recortar = () => {
    if (eventos.length > maximo) eventos = eventos.slice(eventos.length - maximo)
  }
  return {
    anotar(e) {
      eventos.push({ ...e, accion: e.accion.slice(0, 60) })
      recortar()
    },
    tomar() {
      const lote = eventos.slice(0, MAXIMO_POR_LOTE)
      eventos = eventos.slice(lote.length)
      return lote
    },
    devolver(lote) {
      eventos = [...lote, ...eventos]
      recortar()
    },
    get tamano() {
      return eventos.length
    },
  }
}

/**
 * Lleva la pantalla actual para anotar, al navegar, cuánto se estuvo en la
 * anterior.
 */
export function crearSeguimientoDePantalla() {
  let actual: { ruta: string; desde: number } | null = null
  return {
    /** El evento `navegar` hacia `ruta` (null si es la misma pantalla). */
    navegar(ruta: string, titulo: string | undefined, ahora: number): EventoPantalla | null {
      if (actual?.ruta === ruta) return null
      const anterior = actual
      actual = { ruta, desde: ahora }
      return {
        accion: 'navegar',
        pantalla: ruta,
        fecha: ahora,
        ...(anterior ? { duracionMs: Math.max(0, ahora - anterior.desde) } : {}),
        detalle: { ...(titulo ? { titulo } : {}), ...(anterior ? { desde: anterior.ruta } : {}) },
      }
    },
    get ruta() {
      return actual?.ruta ?? null
    },
    reiniciar() {
      actual = null
    },
  }
}
