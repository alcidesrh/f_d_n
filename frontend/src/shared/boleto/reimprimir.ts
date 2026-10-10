import { fetchBoletos, fetchComprobante } from '@/core/venta/api'
import { agruparParaTicket } from '@/core/venta/reasignacion'
import { imprimirTicket } from './ticket'

/**
 * Reimprime el ticket de boletos ya vendidos: uno por venta y viaje, en
 * orden. Devuelve cuántos tickets se mandaron a imprimir.
 */
export async function reimprimirBoletos(ids: number[]): Promise<number> {
  const grupos = agruparParaTicket(await fetchBoletos(ids))
  for (const g of grupos) {
    await imprimirTicket(await fetchComprobante(g.venta, g.boletos))
  }

  return grupos.length
}
