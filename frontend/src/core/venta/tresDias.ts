/**
 * Reglas puras de la vista de tres días de la venta: el día elegido en el
 * centro y sus vecinos a los lados, alineados por la hora a la que sube el
 * cliente (no la del origen de la ruta).
 */
import type { SalidaResumen } from './types'

const ZONA = 'America/Guatemala'
const horaGT = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: ZONA })

/** Cuándo sube el cliente: su parada si se conoce, si no la estación elegida o el origen. */
export const subidaDe = (r: SalidaResumen): string => r.viaje?.sube.hora ?? r.salidaEstacion ?? r.salida

/** Hora y minutos (0–23, 0–59) de un instante, en la hora de Guatemala. */
export function horaMinuto(iso: string): [number, number] {
  const [h, m] = horaGT.format(new Date(iso)).split(':').map(Number)
  return [h! % 24, m!]
}

/** "14:30" en la hora de Guatemala: el tiempo compacto de las fichas. */
export const reloj = (iso: string) => horaMinuto(iso).map((n) => String(n).padStart(2, '0')).join(':')

export interface FilaHora {
  hora: number
  /** Una lista por día, en el orden recibido (anterior, elegido, siguiente). */
  dias: SalidaResumen[][]
}

/**
 * Las horas en que sube alguien en cualquiera de los días, en orden, con las
 * salidas de cada día en esa hora (por minuto). Las horas vacías en los tres no se pintan.
 */
export function filasPorHora(dias: readonly (readonly SalidaResumen[])[]): FilaHora[] {
  const filas = new Map<number, SalidaResumen[][]>()
  dias.forEach((lista, i) => {
    for (const r of lista) {
      const [h] = horaMinuto(subidaDe(r))
      const fila = filas.get(h) ?? dias.map(() => [])
      fila[i]!.push(r)
      filas.set(h, fila)
    }
  })
  const porMinuto = (a: SalidaResumen, b: SalidaResumen) => Date.parse(subidaDe(a)) - Date.parse(subidaDe(b))
  return [...filas]
    .sort(([a], [b]) => a - b)
    .map(([hora, listas]) => ({ hora, dias: listas.map((l) => l.sort(porMinuto)) }))
}

/** El mismo día movido `delta` días (a medianoche local). */
export function diaVecino(d: Date, delta: number): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate() + delta)
}

/** Ocupado del tramo, de 0 a 1; null si no se conoce la capacidad. */
export function ocupacion(r: SalidaResumen): number | null {
  return r.capacidad ? Math.min(1, (r.vendidos ?? 0) / r.capacidad) : null
}

/** "lleno" desde el 90 %, "alto" desde el 60 %: cambia el tono de la barra. */
export function nivelOcupacion(r: SalidaResumen): 'lleno' | 'alto' | null {
  const f = ocupacion(r)
  return f == null ? null : f >= 0.9 ? 'lleno' : f >= 0.6 ? 'alto' : null
}
