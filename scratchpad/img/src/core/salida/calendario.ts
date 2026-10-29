/**
 * Calendario de salidas: días en el eje X (la fecha elegida en el centro),
 * horas del día en el Y y en cada cruce las salidas de esa hora, con el color
 * de su ruta y lo ocupado (asientos o porciento). Filtro → query de
 * `/api/gestion-salidas/calendario`, la ventana de días, la matriz día × hora,
 * la leyenda de rutas y los totales. Puro.
 */
import { aDia, deDia } from './filtro'
import { sumarDias } from './programacion'
import type { EstadoSalida, RutaCalendario, SalidaCalendario } from './types'

/** Días visibles (impar: la fecha elegida queda en el centro). */
export const VENTANAS = [7, 15, 31] as const
export type Ventana = (typeof VENTANAS)[number]

/** Lo que muestra cada salida: asientos comprometidos o porciento de la capacidad. */
export type ModoOcupacion = 'asientos' | 'porciento'

export interface FiltroCalendario {
  /** Centro del eje X, `AAAA-MM-DD`. */
  fecha: string
  dias: Ventana
  /** Vacío = todos. */
  estados: EstadoSalida[]
  empresa: number | null
  ruta: number | null
  bus: number | null
}

/** Por defecto todo menos las anuladas. */
export const ESTADOS_CALENDARIO: EstadoSalida[] = ['programada', 'abordando', 'iniciada', 'finalizada']

export function filtroCalendarioInicial(hoy = new Date(), dias: Ventana = 7): FiltroCalendario {
  return { fecha: aDia(hoy), dias, estados: [...ESTADOS_CALENDARIO], empresa: null, ruta: null, bus: null }
}

/** Días del eje X (`AAAA-MM-DD`) con `fecha` en el centro. */
export function ventana(fecha: string, dias: number): string[] {
  const antes = Math.floor((dias - 1) / 2)
  return Array.from({ length: dias }, (_, i) => sumarDias(fecha, i - antes))
}

/** Query de `/gestion-salidas/calendario`: el rango de la ventana y el resto del filtro (estado vacío = todos). */
export function aQueryCalendario(f: FiltroCalendario): string {
  const dias = ventana(f.fecha, f.dias)
  const q = new URLSearchParams({ desde: dias[0]!, hasta: dias[dias.length - 1]!, estado: f.estados.join(',') })
  if (f.empresa) q.set('empresa', String(f.empresa))
  if (f.ruta) q.set('ruta', String(f.ruta))
  if (f.bus) q.set('bus', String(f.bus))
  return q.toString()
}

/** Cuánto ocupa (0–1, sin pasar de 1) o null si no se conoce la capacidad. */
export function fraccion(s: Pick<SalidaCalendario, 'vendidos' | 'capacidad'>): number | null {
  return s.capacidad ? Math.min(1, s.vendidos / s.capacidad) : null
}

/** El número de la celda: asientos comprometidos o el porciento (sin capacidad, los asientos). */
export function textoOcupacion(s: Pick<SalidaCalendario, 'vendidos' | 'capacidad'>, modo: ModoOcupacion): string {
  const f = fraccion(s)
  return modo === 'porciento' && f !== null ? `${Math.round((s.vendidos / s.capacidad!) * 100)}%` : String(s.vendidos)
}

/** «23 de 46 asientos (50 %)». */
export function detalleOcupacion(s: Pick<SalidaCalendario, 'vendidos' | 'capacidad'>): string {
  if (!s.capacidad) return `${s.vendidos} asiento(s) comprometido(s)`
  return `${s.vendidos} de ${s.capacidad} asientos (${Math.round((s.vendidos / s.capacidad) * 100)} %)`
}

/** Nivel de ocupación para resaltar: vacía (< 25 %), media, alta (≥ 80 %), llena. */
export function nivel(s: Pick<SalidaCalendario, 'vendidos' | 'capacidad'>): 'vacia' | 'media' | 'alta' | 'llena' | null {
  const f = fraccion(s)
  if (f === null) return null
  return f >= 1 ? 'llena' : f >= 0.8 ? 'alta' : f < 0.25 ? 'vacia' : 'media'
}

/**
 * Salidas por día y hora local: `dia → [24 listas]`, cada lista por minuto
 * y, a la misma hora, por ruta. Solo los días de `dias`.
 */
export function matriz(items: readonly SalidaCalendario[], dias: readonly string[]): Map<string, SalidaCalendario[][]> {
  const m = new Map(dias.map((d) => [d, Array.from({ length: 24 }, () => [] as SalidaCalendario[])]))
  for (const s of items) {
    const f = new Date(s.fecha)
    m.get(aDia(f))?.[f.getHours()]!.push(s)
  }
  for (const horas of m.values())
    for (const lista of horas) lista.sort((a, b) => a.fecha.localeCompare(b.fecha) || a.rutaId - b.rutaId || a.id - b.id)
  return m
}

export interface Totales {
  salidas: number
  vendidos: number
  /** Asientos ofrecidos (las anuladas no ofrecen). */
  capacidad: number
}

/** Totales de un conjunto de salidas (un día, la ventana). */
export function totales(items: readonly SalidaCalendario[]): Totales {
  let vendidos = 0
  let capacidad = 0
  for (const s of items) {
    vendidos += s.vendidos
    if (s.estado !== 'cancelada') capacidad += s.capacidad ?? 0
  }
  return { salidas: items.length, vendidos, capacidad }
}

/** Tono (0–359) de una ruta: estable por id y bien separado entre ids vecinos (ángulo áureo). */
export const tonoRuta = (id: number) => Math.round((id * 137.508) % 360)

export interface EntradaLeyenda {
  ruta: RutaCalendario
  tono: number
  salidas: number
}

/** Las rutas con salidas en la ventana, de la que más tiene a la que menos. */
export function leyenda(items: readonly SalidaCalendario[], rutas: readonly RutaCalendario[]): EntradaLeyenda[] {
  const n = new Map<number, number>()
  for (const s of items) n.set(s.rutaId, (n.get(s.rutaId) ?? 0) + 1)
  return rutas
    .filter((r) => n.has(r.id))
    .map((r) => ({ ruta: r, tono: tonoRuta(r.id), salidas: n.get(r.id)! }))
    .sort((a, b) => b.salidas - a.salidas || a.ruta.nombre.localeCompare(b.ruta.nombre))
}

/** «GUA → SEP» con las siglas; si faltan, los nombres. */
export const puntas = (r: Pick<RutaCalendario, 'origen' | 'destino' | 'origenAlias' | 'destinoAlias'>) =>
  `${r.origenAlias || r.origen || '?'} → ${r.destinoAlias || r.destino || '?'}`

/** Dónde cae `ahora` en el calendario: el día, la hora y qué fracción de esa hora pasó. */
export function ahoraEn(ahora: Date): { dia: string; hora: number; fraccion: number } {
  return { dia: aDia(ahora), hora: ahora.getHours(), fraccion: (ahora.getMinutes() * 60 + ahora.getSeconds()) / 3600 }
}

/** Mueve el centro `n` días. */
export const desplazar = (fecha: string, n: number) => sumarDias(fecha, n)

/** ¿`dia` cae en la ventana de `f`? */
export function enVentana(f: Pick<FiltroCalendario, 'fecha' | 'dias'>, dia: string): boolean {
  const d = ventana(f.fecha, f.dias)
  return dia >= d[0]! && dia <= d[d.length - 1]!
}

/** Fin de semana (sáb o dom), para atenuar la columna. */
export const finDeSemana = (dia: string) => [0, 6].includes(deDia(dia).getDay())
