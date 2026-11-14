import { describe, expect, it } from 'vitest'
import { pedidoDeModificacion, tomadosEnModificacion, type AsientoDestino } from '@/modificacion'

const asiento = (salida: number, id: number, extra: Partial<AsientoDestino> = {}): AsientoDestino => ({
  salida,
  trayecto: 7,
  asiento: id,
  numero: id,
  clase: 'A',
  centavos: 10000,
  hora: '2026-12-01T08:30:00-06:00',
  origen: 'Guatemala',
  destino: 'Flores',
  ...extra,
})

describe('modificación de una compra', () => {
  it('arma el pedido: por boleto a dónde pasa, y los asientos que se agregan', () => {
    const pedido = pedidoDeModificacion({ 41: asiento(3, 12), 42: asiento(4, 5, { trayecto: 9 }) }, [asiento(3, 13)])

    expect(pedido).toEqual({
      cambios: [
        { boleto: 41, salida: 3, trayecto: 7, asiento: 12 },
        { boleto: 42, salida: 4, trayecto: 9, asiento: 5 },
      ],
      nuevos: [{ salida: 3, trayecto: 7, asiento: 13 }],
    })
  })

  it('sin cambios ni asientos nuevos, el pedido va vacío', () => {
    expect(pedidoDeModificacion({}, [])).toEqual({ cambios: [], nuevos: [] })
  })

  it('un asiento ya elegido en la modificación no se elige otra vez en la misma salida', () => {
    const tomados = tomadosEnModificacion([asiento(3, 12)], [asiento(3, 13)])

    expect(tomados.has('3-12')).toBe(true)
    expect(tomados.has('3-13')).toBe(true)
    expect(tomados.has('4-12')).toBe(false)
  })
})
