/**
 * Mantenimiento de flota (ADR-065): lo que devuelve `/api/mantenimiento/estado`
 * y cómo se muestra. El estado lo calcula el backend (`ReglasMantenimiento`);
 * aquí solo se filtra, se cuenta y se pone en palabras. Puro.
 */

export type EstadoMantenimiento = 'vencido' | 'por_vencer' | 'sin_registro' | 'al_dia'

export interface PlanMantenimiento {
  id: number
  nombre: string
  cadaKm: number | null
  cadaDias: number | null
}

/** En qué va un bus con un plan; `faltan*` negativos: ya se pasó por tanto. */
export interface EstadoDePlan {
  plan: number
  estado: EstadoMantenimiento
  faltanKm: number | null
  faltanDias: number | null
  /** `AAAA-MM-DD` de la última orden con ese plan. */
  ultimaFecha: string | null
  ultimoKm: number | null
}

export interface BusEnMantenimiento {
  id: number
  codigo: string
  matricula: string | null
  empresaId: number | null
  odometroKm: number
  /** El que más urge de sus planes. */
  estado: EstadoMantenimiento
  planes: EstadoDePlan[]
}

export interface RespuestaMantenimiento {
  /** ISO con zona */
  generado: string
  planes: PlanMantenimiento[]
  buses: BusEnMantenimiento[]
  /** Registrar órdenes, mantener los planes y corregir el odómetro (editar el bus). */
  puede: { registrar: boolean; planes: boolean; odometro: boolean }
}

/** Del que más urge al que menos. */
export const ESTADOS: readonly EstadoMantenimiento[] = ['vencido', 'por_vencer', 'sin_registro', 'al_dia']

export const ESTADO: Record<EstadoMantenimiento, { etiqueta: string; severidad: 'danger' | 'warn' | 'secondary' | 'success' }> = {
  vencido: { etiqueta: 'Vencido', severidad: 'danger' },
  por_vencer: { etiqueta: 'Por vencer', severidad: 'warn' },
  sin_registro: { etiqueta: 'Sin registro', severidad: 'secondary' },
  al_dia: { etiqueta: 'Al día', severidad: 'success' },
}

const km = (n: number) => `${Math.abs(n).toLocaleString('es-GT')} km`
const dias = (n: number) => (Math.abs(n) === 1 ? '1 día' : `${Math.abs(n)} días`)

/** «Cada 10,000 km o 180 días». */
export function intervalo(p: Pick<PlanMantenimiento, 'cadaKm' | 'cadaDias'>): string {
  const partes = [p.cadaKm ? km(p.cadaKm) : null, p.cadaDias ? dias(p.cadaDias) : null].filter(Boolean)
  return partes.length ? `Cada ${partes.join(' o ')}` : 'Sin intervalo'
}

/** «vencido hace 320 km», «le faltan 800 km o 12 días», «nunca se registró» (como `ReglasMantenimiento::detalle`). */
export function detalle(e: Pick<EstadoDePlan, 'estado' | 'faltanKm' | 'faltanDias'>): string {
  if (e.estado === 'sin_registro') return 'nunca se registró'
  if (e.estado === 'vencido') {
    const pasado = [e.faltanKm !== null && e.faltanKm < 0 ? km(e.faltanKm) : null, e.faltanDias !== null && e.faltanDias < 0 ? dias(e.faltanDias) : null].filter(Boolean)
    return pasado.length ? `vencido hace ${pasado.join(' y ')}` : 'vence hoy'
  }
  const falta = [e.faltanKm !== null ? km(e.faltanKm) : null, e.faltanDias !== null ? dias(e.faltanDias) : null].filter(Boolean)
  return falta.length ? `le faltan ${falta.join(' o ')}` : 'al día'
}

/** Cuántos buses hay en cada estado. */
export function conteo(buses: readonly Pick<BusEnMantenimiento, 'estado'>[]): Record<EstadoMantenimiento, number> {
  const c: Record<EstadoMantenimiento, number> = { vencido: 0, por_vencer: 0, sin_registro: 0, al_dia: 0 }
  for (const b of buses) c[b.estado]++
  return c
}

/**
 * Los buses de ese estado (todos sin filtro) cuyo código o matrícula lleva
 * el texto, primero los que más urgen y, dentro de un estado, por código.
 */
export function filtrar<T extends Pick<BusEnMantenimiento, 'estado' | 'codigo' | 'matricula'>>(buses: readonly T[], f: { estado: EstadoMantenimiento | null; texto: string }): T[] {
  const texto = f.texto.trim().toLowerCase()
  return buses
    .filter((b) => (f.estado === null || b.estado === f.estado) && (!texto || b.codigo.toLowerCase().includes(texto) || (b.matricula ?? '').toLowerCase().includes(texto)))
    .sort((a, b) => ESTADOS.indexOf(a.estado) - ESTADOS.indexOf(b.estado) || a.codigo.localeCompare(b.codigo, 'es', { numeric: true }))
}

/** Lo que pide atención de un bus (vencido o por vencer), lo vencido primero. */
export function pendientes(b: Pick<BusEnMantenimiento, 'planes'>): EstadoDePlan[] {
  return b.planes.filter((p) => p.estado === 'vencido' || p.estado === 'por_vencer').sort((x, y) => ESTADOS.indexOf(x.estado) - ESTADOS.indexOf(y.estado))
}
