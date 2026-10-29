/**
 * Reglas puras de la vista de calendario de la venta: el día elegido o, con
 * un rango, una columna por día, alineados por la hora a la que sube el
 * cliente (no la del origen de la ruta).
 */
import type { SalidaResumen } from './types'

const ZONA = 'America/Guatemala'
const horaGT = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: ZONA })
const diaGT = new Intl.DateTimeFormat('en-CA', { timeZone: ZONA })

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
  /** Una lista por día, en el orden recibido. */
  dias: SalidaResumen[][]
}

/**
 * Las horas en que sube alguien en cualquiera de los días, en orden, con las
 * salidas de cada día en esa hora (por minuto). Las horas vacías en todos no se pintan.
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

/** Los días (a medianoche local) de `desde` a `hasta`, ambos incluidos. */
export function diasDelRango(desde: Date, hasta: Date): Date[] {
  const dias: Date[] = []
  for (let d = diaVecino(desde, 0); d <= hasta; d = diaVecino(d, 1)) dias.push(d)
  return dias
}

/** Las salidas de cada día (`YYYY-MM-DD`) por el día en que sube el cliente, en la hora de Guatemala. */
export function porDia(salidas: readonly SalidaResumen[], dias: readonly string[]): SalidaResumen[][] {
  const listas = new Map(dias.map((d) => [d, [] as SalidaResumen[]]))
  for (const r of salidas) listas.get(diaGT.format(new Date(subidaDe(r))))?.push(r)
  return dias.map((d) => listas.get(d)!)
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
