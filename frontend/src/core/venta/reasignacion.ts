/**
 * Reglas puras de la reasignación de boletos (espejo de lo que exige
 * `App\Venta\ReglasBoletos`; el backend es quien manda).
 */
import type { BoletoOperable, Importe, SalidaDetalle, SalidaResumen } from './types'

/**
 * Salidas a las que se pueden pasar los boletos: todavía no salen, siguen
 * programadas o abordando y, salvo lo vendido en la página web, son de la
 * misma empresa que los boletos.
 */
export function salidasReasignables(salidas: SalidaResumen[], boletos: BoletoOperable[], ahora: Date): SalidaResumen[] {
  const web = boletos.every((b) => b.venta.canal === 'web')
  const empresas = new Set(boletos.map((b) => b.salida.empresa?.id ?? null))
  return salidas.filter(
    (s) =>
      (s.estado === 'programada' || s.estado === 'abordando') &&
      new Date(s.salida).getTime() > ahora.getTime() &&
      (web || (empresas.size === 1 && empresas.has(s.empresa?.id ?? null))),
  )
}

export interface Pareja {
  boleto: BoletoOperable
  /** Asiento nuevo (el de la misma posición en la selección), si ya se eligió. */
  asiento: number | null
  /** Lo que cuesta el asiento nuevo, si se cotizó. */
  precio: Importe | null
  /** Cuesta lo mismo que el boleto (solo si ya se cotizó). */
  igual: boolean | null
}

/** Empareja cada boleto con el asiento elegido en su posición y compara precios. */
export function emparejar(boletos: BoletoOperable[], seleccion: number[], precios: Map<number, Importe>): Pareja[] {
  return boletos.map((boleto, i) => {
    const asiento = seleccion[i] ?? null
    const precio = asiento === null ? null : (precios.get(asiento) ?? null)
    return {
      boleto,
      asiento,
      precio,
      igual: precio === null || boleto.precio === null ? null : precio.centavos === boleto.precio.centavos && precio.moneda === boleto.precio.moneda,
    }
  })
}

/** Todos los boletos tienen su asiento y cuestan lo mismo. */
export const listaParaReasignar = (parejas: Pareja[]): boolean => parejas.length > 0 && parejas.every((p) => p.asiento !== null && p.igual === true)

/**
 * Un ticket por venta y viaje (el ticket lleva un solo viaje): los boletos
 * agrupados para reimprimir.
 */
export function agruparParaTicket(boletos: BoletoOperable[]): Array<{ venta: number; boletos: number[] }> {
  const grupos = new Map<string, { venta: number; boletos: number[] }>()
  for (const b of boletos) {
    const clave = `${b.venta.id}/${b.salida.id}/${b.trayecto.id}`
    const g = grupos.get(clave) ?? { venta: b.venta.id, boletos: [] }
    g.boletos.push(b.id)
    grupos.set(clave, g)
  }
  return [...grupos.values()]
}

/** Motivo común por el que no se puede operar sobre la selección; null si todos se pueden. */
export function motivoNoOperable(boletos: BoletoOperable[]): string | null {
  const malo = boletos.find((b) => !b.operable)
  return malo ? `Asiento ${malo.asiento.numero}: ${malo.motivo ?? 'no se puede operar'}` : null
}

/**
 * Dónde sube y baja el pasajero en la salida elegida, si ofrece el mismo
 * tramo que tenía el boleto (es lo más probable que cueste lo mismo).
 */
export function tramoDelBoleto(detalle: Pick<SalidaDetalle, 'trayectos'>, boleto: BoletoOperable): { sube: number; baja: number } | null {
  const t = detalle.trayectos.find((x) => x.origen === boleto.trayecto.origenId && x.destino === boleto.trayecto.destinoId)
  return t ? { sube: t.origen, baja: t.destino } : null
}
