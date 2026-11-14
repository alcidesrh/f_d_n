/**
 * Reglas puras de la modificación de una compra («Mis compras»): lo que el
 * cliente va armando en la página y el pedido que se envía al backend.
 */
import type { PedidoModificacion } from './tipos'

/** Asiento elegido como destino de un cambio o como asiento nuevo. */
export interface AsientoDestino {
  salida: number
  trayecto: number
  /** Id del asiento. */
  asiento: number
  numero: number
  clase: 'A' | 'B'
  centavos: number
  /** Hora donde sube (o la del origen del bus si no se conoce). */
  hora: string
  origen: string
  destino: string
}

/** `salida-asiento` ya elegidos en esta modificación: no se eligen dos veces. */
export function tomadosEnModificacion(cambios: readonly AsientoDestino[], nuevos: readonly AsientoDestino[]): Set<string> {
  return new Set([...cambios, ...nuevos].map((a) => `${a.salida}-${a.asiento}`))
}

/** El pedido para el backend: por boleto, a dónde pasa; y los asientos que se agregan. */
export function pedidoDeModificacion(cambios: Readonly<Record<number, AsientoDestino>>, nuevos: readonly AsientoDestino[]): PedidoModificacion {
  const destino = (a: AsientoDestino) => ({ salida: a.salida, trayecto: a.trayecto, asiento: a.asiento })
  return {
    cambios: Object.entries(cambios).map(([boleto, a]) => ({ boleto: Number(boleto), ...destino(a) })),
    nuevos: nuevos.map(destino),
  }
}
