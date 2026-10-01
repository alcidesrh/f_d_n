/**
 * Mensaje para el cliente a partir de un error de la API: el código del
 * backend se traduce (`errores.<codigo>`); si no hay traducción se muestra
 * el mensaje del backend (en español). En los rechazos del banco se agrega
 * el motivo que dio el banco, tal cual: es lo más útil para el cliente.
 */
import { ErrorPublico } from './api'

type T = (clave: string, valores?: Record<string, unknown>) => string
type Te = (clave: string) => boolean

export interface MensajeError {
  titulo: string
  detalle: string | null
}

/** Códigos cuyo mensaje del backend trae información propia (motivo del banco, asientos…). */
const CON_DETALLE = new Set(['pago_rechazado', 'reembolsado', 'asientos_no_disponibles', 'venta_invalida', 'carrito_invalido', 'comprador_invalido', 'tarjeta_invalida', 'facturacion_invalida'])

export function mensajeDeError(e: unknown, t: T, te: Te, valores: Record<string, unknown> = {}): MensajeError {
  if (!(e instanceof ErrorPublico)) {
    return { titulo: t('errores.general'), detalle: e instanceof Error ? e.message || null : null }
  }
  const clave = `errores.${e.codigo}`
  const traducido = te(clave) ? t(clave, valores) : null
  if (!traducido) return { titulo: e.message || t('errores.general'), detalle: null }
  const detalle = CON_DETALLE.has(e.codigo) && e.message && e.message !== traducido ? e.message : null
  return { titulo: traducido, detalle: detalle && e.codigo === 'pago_rechazado' ? t('errores.detalleBanco', { mensaje: detalle }) : detalle }
}
