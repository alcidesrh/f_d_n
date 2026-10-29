/**
 * Autorizaciones (ADR-040), reglas puras: reconocer un bloqueo que admite
 * autorización, armar lo que se pide, y los textos y etiquetas de cada estado
 * y regla. Quien decide es el backend; esto solo muestra.
 */
import { HttpError } from '@/core/http'
import type { Autorizable, Autorizacion, Dispensa, EstadoAutorizacion, ResumenAutorizaciones } from './types'

type Severidad = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'

export const ESTADOS: Record<EstadoAutorizacion, { etiqueta: string; severidad: Severidad }> = {
  pendiente: { etiqueta: 'Pendiente', severidad: 'warn' },
  aprobada: { etiqueta: 'Aprobada', severidad: 'success' },
  rechazada: { etiqueta: 'Rechazada', severidad: 'danger' },
  usada: { etiqueta: 'Usada', severidad: 'info' },
  retirada: { etiqueta: 'Retirada', severidad: 'secondary' },
  vencida: { etiqueta: 'Vencida', severidad: 'secondary' },
}

export const DISPENSAS: Record<Dispensa, { etiqueta: string; icono: string; explicacion: string }> = {
  permiso: { etiqueta: 'Sin permiso', icono: 'lock-outline', explicacion: 'No tiene el permiso para esto en esta empresa o estación.' },
  limite: { etiqueta: 'Límite diario superado', icono: 'data-usage', explicacion: 'Llegó al máximo que su rol permite por día.' },
  hora: { etiqueta: 'Fuera de hora', icono: 'schedule-outline', explicacion: 'La salida ya pasó su hora: el pasajero perdió el bus.' },
}

/** El bloqueo de una operación, si se puede hacer con una autorización: lo que hay que pedir y el mensaje del bloqueo. */
export function bloqueoAutorizable(error: unknown): { autorizable: Autorizable; mensaje: string } | null {
  if (!(error instanceof HttpError) || typeof error.body !== 'object' || error.body === null) return null
  const a = (error.body as { autorizable?: unknown }).autorizable
  if (!esAutorizable(a)) return null
  return { autorizable: a, mensaje: error.message }
}

function esAutorizable(a: unknown): a is Autorizable {
  if (typeof a !== 'object' || a === null) return false
  const x = a as Record<string, unknown>
  return typeof x.capacidad === 'string' && typeof x.dispensa === 'string' && (x.sujeto === 'boletos' || x.sujeto === 'salida') && Array.isArray(x.ids) && x.ids.length > 0
}

const OPERACIONES: Record<string, string> = {
  'boleto.anular': 'anular boletos',
  'boleto.reasignar': 'reasignar boletos',
  'venta.cortesia': 'emitir cortesías',
}

/**
 * Lo que se pide sobre boletos sin haber intentado la operación (p. ej. sin
 * el permiso el botón ofrece pedirla directo).
 */
export function autorizableBoletos(capacidad: 'boleto.anular' | 'boleto.reasignar', dispensa: Dispensa, ids: number[]): Autorizable {
  const unicos = [...new Set(ids)].sort((a, b) => a - b)
  return { capacidad, operacion: OPERACIONES[capacidad] ?? capacidad, dispensa, dispensaNombre: DISPENSAS[dispensa].etiqueta, sujeto: 'boletos', ids: unicos, cantidad: unicos.length }
}

/** «Anular 2 boletos», «Emitir 1 cortesía en la salida 1230». */
export function queSePide(a: Pick<Autorizable, 'capacidad' | 'operacion' | 'sujeto' | 'ids' | 'cantidad'>): string {
  const n = a.cantidad
  if (a.sujeto === 'salida') {
    const que = a.capacidad === 'venta.cortesia' ? (n === 1 ? '1 cortesía' : `${n} cortesías`) : `${n} asiento(s)`
    return `Emitir ${que} en la salida ${a.ids[0]}`
  }
  const verbo = a.operacion.split(' ')[0] ?? a.operacion
  return `${verbo.charAt(0).toUpperCase()}${verbo.slice(1)} ${n === 1 ? '1 boleto' : `${n} boletos`}`
}

/** Lo que muestra la campana de la cabecera: lo que espera al usuario (por resolver y pases listos para usar). */
export const pendientesDe = (r: ResumenAutorizaciones | null) => (r ? r.porResolver + r.aprobadas : 0)

/** «vence en 3 h», «vence en 25 min», «venció». Solo para las abiertas. */
export function vigencia(a: Pick<Autorizacion, 'estado' | 'venceEn'>, ahora = new Date()): string | null {
  if (a.estado !== 'pendiente' && a.estado !== 'aprobada') return null
  const min = Math.round((new Date(a.venceEn).getTime() - ahora.getTime()) / 60_000)
  if (min <= 0) return 'venció'
  return min >= 60 ? `vence en ${Math.round(min / 60)} h` : `vence en ${min} min`
}

/** ¿Este pase aprobado sirve para operar esos boletos? (para avisar que ya puede repetir la operación) */
export function cubreBoletos(a: Pick<Autorizacion, 'estado' | 'capacidad' | 'sujeto' | 'ids'>, capacidad: string, ids: number[]): boolean {
  return a.estado === 'aprobada' && a.capacidad === capacidad && a.sujeto === 'boletos' && ids.every((id) => a.ids.includes(id))
}
