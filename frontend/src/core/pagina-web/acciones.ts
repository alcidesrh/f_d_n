/**
 * Acciones sobre una compra de la página web (dashboard, menú de cada fila):
 * qué se puede hacer y por qué no, el estado que se muestra (el del pago o,
 * si se completó, el de sus boletos) y qué boletos toma cada acción. Lo que
 * admite cada boleto (anular, reasignar, posponer) lo dice el backend
 * (`acciones`, el workflow del boleto, ADR-037); aquí se junta por compra y
 * se suma el permiso. Funciones puras.
 */
import type { BoletoCompra, CompraWeb, EstadoPago, TransicionCompra, VentaCompra } from './types'

export type EstadoCompra = EstadoPago | 'pospuesta' | 'anulada'

export const ESTADOS_COMPRA: Record<EstadoCompra, { etiqueta: string; inicial: string }> = {
  completado: { etiqueta: 'Completada', inicial: 'C' },
  rechazado: { etiqueta: 'Rechazada', inicial: 'R' },
  autenticacion: { etiqueta: 'Sin terminar', inicial: 'S' },
  aprobado: { etiqueta: 'Cobrada (registrando)', inicial: 'Co' },
  reembolsado: { etiqueta: 'Reembolsada', inicial: 'Re' },
  reembolso_pendiente: { etiqueta: 'Reembolso pendiente', inicial: 'Rp' },
  pospuesta: { etiqueta: 'Pospuesta', inicial: 'P' },
  anulada: { etiqueta: 'Anulada', inicial: 'A' },
}

const boletos = (c: CompraWeb): BoletoCompra[] => c.ventas.flatMap((v) => v.boletos)
const VIAJAN = ['emitido', 'chequeado', 'transito', 'finalizado']

/**
 * Completada: anulada si todos sus boletos lo están; pospuesta si tiene
 * pospuestos y ninguno que viaje. Si no, el estado del pago.
 */
export function estadoCompra(c: CompraWeb): EstadoCompra {
  const bs = boletos(c)
  if (c.estado !== 'completado' || !bs.length) return c.estado
  if (bs.every((b) => b.estado === 'anulado')) return 'anulada'
  if (bs.some((b) => b.estado === 'pospuesto') && !bs.some((b) => VIAJAN.includes(b.estado))) return 'pospuesta'
  return 'completado'
}

const admite = (b: BoletoCompra, t: TransicionCompra) => b.acciones[t] === null

/** Ninguno la admite: el motivo del backend si es el mismo para todos (un solo boleto, la misma salida); si no, `general`. */
function ninguno(bs: BoletoCompra[], t: TransicionCompra, general: string): string {
  const motivos = new Set(bs.map((b) => b.acciones[t]))
  return motivos.size === 1 ? ([...motivos][0] ?? general) : general
}

/** Los boletos que anula «Anular»: todos los que todavía se pueden anular. */
export const boletosParaAnular = (c: CompraWeb): number[] => boletos(c).filter((b) => admite(b, 'anular')).map((b) => b.id)

/** Viajes que se pueden reasignar, cada uno con sus boletos (la taquilla reasigna una venta a la vez). */
export function viajesReasignables(c: CompraWeb): Array<{ venta: VentaCompra; etiqueta: string; boletos: number[] }> {
  return c.ventas
    .map((venta, i) => ({
      venta,
      etiqueta: c.ventas.length > 1 ? (i === 0 ? 'Ida' : 'Regreso') : 'Viaje',
      boletos: venta.boletos.filter((b) => admite(b, 'reasignar')).map((b) => b.id),
    }))
    .filter((v) => v.boletos.length > 0)
}

export type AccionCompra = 'ver' | 'boleto' | 'editar' | 'reasignar' | 'posponer' | 'anular' | 'factura' | 'reenviar'

export interface PermisosCompra {
  anular: boolean
  reasignar: boolean
}

/** Por acción: `null` si se puede; si no, el motivo. */
export function disponibilidadCompra(c: CompraWeb, puede: PermisosCompra): Record<AccionCompra, string | null> {
  const completada = c.estado === 'completado' && c.ventas.length > 0 ? null : 'La compra no se completó: no tiene boletos'
  const bs = boletos(c)
  const sinPermiso = (p: boolean) => (p ? null : 'Sin permiso')
  return {
    ver: null,
    boleto: completada,
    editar: completada,
    reasignar:
      completada ??
      sinPermiso(puede.reasignar) ??
      (viajesReasignables(c).length ? null : ninguno(bs, 'reasignar', 'No hay boletos emitidos antes de su hora de salida ni pospuestos')),
    posponer: completada ?? (bs.some((b) => admite(b, 'posponer')) ? null : ninguno(bs, 'posponer', 'No hay boletos emitidos antes de su hora de salida')),
    anular: completada ?? sinPermiso(puede.anular) ?? (boletosParaAnular(c).length ? null : ninguno(bs, 'anular', 'No hay boletos que se puedan anular')),
    factura:
      completada ??
      (c.ventas.some((v) => (v.facturacion === 'certificada' || v.facturacion === 'pendiente') && v.boletos.some((b) => b.estado !== 'anulado'))
        ? null
        : 'No hay viajes por facturar (anulados o sin cobro)'),
    reenviar: completada,
  }
}
