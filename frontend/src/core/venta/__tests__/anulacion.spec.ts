import { describe, expect, it } from 'vitest'
import { facturasEnAnulacion } from '../anulacion'
import type { BoletoOperable } from '../types'

const boleto = (id: number, venta: Partial<BoletoOperable['venta']> = {}): BoletoOperable =>
  ({
    id,
    venta: { id: 7, canal: 'estacion', cortesia: false, estadoFacturacion: 'certificada', factura: { serie: 'A1B2', numero: 99 }, boletosFactura: [1, 2, 3], cliente: null, ...venta },
  }) as BoletoOperable

describe('facturas que se tocan al anular', () => {
  it('una factura conjunta dice qué boletos quedan en la nueva', () => {
    expect(facturasEnAnulacion([boleto(1)], [1])).toEqual([{ venta: 7, factura: 'A1B2-99', cubre: 3, quedan: [2, 3] }])
  })

  it('con todos sus boletos no queda nada por refacturar', () => {
    expect(facturasEnAnulacion([boleto(1), boleto(2), boleto(3)], [1, 2, 3])[0]?.quedan).toEqual([])
  })

  it('una venta sin factura no aparece y una por certificar no tiene número', () => {
    const r = facturasEnAnulacion([boleto(1, { boletosFactura: [] }), boleto(5, { id: 8, factura: null, boletosFactura: [5, 6] })], [1, 5])
    expect(r).toEqual([{ venta: 8, factura: null, cubre: 2, quedan: [6] }])
  })
})
