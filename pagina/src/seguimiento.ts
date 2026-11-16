/** «¿Dónde va mi bus?» (ADR-065): reglas puras de lo que muestra «Mis compras». */

export interface ParadaSeguimiento {
  enclave: number
  nombre: string
  /** Epoch en segundos; null si no se conoce. */
  programada: number | null
  estimada: number | null
  paso: boolean
}

export interface TramoSeguimiento {
  salida: number
  estado: string
  bus: string | null
  asientos: number[]
  /** El seguimiento se muestra (cerca de la hora del viaje o en camino). */
  visible: boolean
  fuente: 'gps' | 'horario'
  atrasoMinutos: number | null
  lectura: number | null
  posicion: { lat: number; lng: number } | null
  /** De donde sube a donde baja. */
  paradas: ParadaSeguimiento[]
}

export interface SeguimientoViaje {
  generado: number
  tramos: TramoSeguimiento[]
}

/** Horas antes y después de la salida en que la página consulta el seguimiento de un viaje. */
export const ANTES_HORAS = 3
export const DESPUES_HORAS = 30

/** Si vale la pena preguntar por el seguimiento de un viaje que sale a `fechaIso`. */
export function consultable(fechaIso: string, ahoraMs: number): boolean {
  const salida = new Date(fechaIso).getTime()
  return ahoraMs >= salida - ANTES_HORAS * 3600_000 && ahoraMs <= salida + DESPUES_HORAS * 3600_000
}

/** Hasta cuántos minutos de diferencia se dice «a tiempo». */
export const TOLERANCIA_MINUTOS = 5

export type Puntualidad = 'aTiempo' | 'atrasado' | 'adelantado' | 'horario'

export function puntualidad(t: Pick<TramoSeguimiento, 'fuente' | 'atrasoMinutos'>): Puntualidad {
  if (t.fuente !== 'gps' || t.atrasoMinutos === null) return 'horario'
  if (t.atrasoMinutos > TOLERANCIA_MINUTOS) return 'atrasado'
  return t.atrasoMinutos < -TOLERANCIA_MINUTOS ? 'adelantado' : 'aTiempo'
}

/** Dónde sube y dónde baja, con la hora que se le muestra: la estimada si hay; si no, la del horario. */
export function extremos(t: TramoSeguimiento): { sube: ParadaSeguimiento; baja: ParadaSeguimiento } | null {
  const sube = t.paradas[0]
  const baja = t.paradas[t.paradas.length - 1]
  return sube && baja && sube !== baja ? { sube, baja } : null
}

export const horaMostrada = (p: ParadaSeguimiento): number | null => p.estimada ?? p.programada

/** Enlace para abrir la posición del bus en la app de mapas del teléfono. */
export const enlaceMapa = (p: { lat: number; lng: number }) => `https://www.google.com/maps/search/?api=1&query=${p.lat},${p.lng}`
