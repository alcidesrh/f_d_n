/**
 * Reglas puras de la anulación de boletos (espejo de
 * `App\Venta\Anulacion\AnulacionBoletos`; el backend es quien manda).
 */
import type { BoletoOperable } from './types'

/** La factura de una venta que se toca al anular parte de sus boletos. */
export interface FacturaEnAnulacion {
  venta: number
  /** "A-123", o null si todavía no está certificada. */
  factura: string | null
  /** Cuántos boletos vivos cubre: con más de uno es conjunta. */
  cubre: number
  /** Los que no se anulan: con alguno, se anula y se certifica otra con ellos. */
  quedan: number[]
}

/** Por cada venta con factura (certificada o por certificar) de los boletos a anular, qué pasa con ella. */
export function facturasEnAnulacion(boletos: BoletoOperable[], seleccion: number[]): FacturaEnAnulacion[] {
  const elegidos = new Set(seleccion)
  const porVenta = new Map<number, FacturaEnAnulacion>()
  for (const b of boletos) {
    const v = b.venta
    if (porVenta.has(v.id) || !v.boletosFactura.length) continue
    porVenta.set(v.id, {
      venta: v.id,
      factura: v.factura ? [v.factura.serie, v.factura.numero].filter((x) => x != null).join('-') || null : null,
      cubre: v.boletosFactura.length,
      quedan: v.boletosFactura.filter((id) => !elegidos.has(id)),
    })
  }
  return [...porVenta.values()]
}
