/** Hora estimada de llegada por parada (ADR-064): `GET /api/seguimiento/salidas/{id}/llegadas`. */

export interface LlegadaParada {
  enclave: number
  nombre: string
  /** Hora del horario (epoch en segundos); null si no se conoce. */
  programada: number | null
  /** Hora estimada (epoch); null si el bus ya pasó o no se conoce. */
  estimada: number | null
  /** El bus ya pasó por la parada. */
  paso: boolean
}

export interface Llegadas {
  /** `gps`: corregidas con la última lectura del bus; `horario`: las del horario. */
  fuente: 'gps' | 'horario'
  /** Minutos de atraso (negativo: adelantado); null sin lectura. */
  atrasoMinutos: number | null
  /** Instante de la lectura usada (epoch); null sin lectura. */
  lectura: number | null
  paradas: LlegadaParada[]
}

/** Hasta cuántos minutos de diferencia se dice «a tiempo». */
export const TOLERANCIA_MINUTOS = 5

export type Puntualidad = 'a_tiempo' | 'atrasado' | 'adelantado' | 'sin_dato'

export function puntualidad(atrasoMinutos: number | null): Puntualidad {
  if (atrasoMinutos === null) return 'sin_dato'
  if (atrasoMinutos > TOLERANCIA_MINUTOS) return 'atrasado'
  return atrasoMinutos < -TOLERANCIA_MINUTOS ? 'adelantado' : 'a_tiempo'
}

/** «45 min» o «1 h 20 min». */
export function minutosTexto(minutos: number): string {
  const m = Math.abs(Math.round(minutos))
  return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h${m % 60 ? ` ${m % 60} min` : ''}`
}

/** «A tiempo», «20 min de atraso», «10 min de adelanto» o «Según horario» (sin lectura del bus). */
export function etiquetaAtraso(atrasoMinutos: number | null): string {
  switch (puntualidad(atrasoMinutos)) {
    case 'sin_dato':
      return 'Según horario'
    case 'a_tiempo':
      return 'A tiempo'
    case 'atrasado':
      return `${minutosTexto(atrasoMinutos!)} de atraso`
    default:
      return `${minutosTexto(atrasoMinutos!)} de adelanto`
  }
}

/** La primera parada por la que el bus no ha pasado. */
export const siguienteParada = (l: Llegadas): LlegadaParada | null => (l.fuente === 'gps' ? (l.paradas.find((p) => !p.paso) ?? null) : null)
