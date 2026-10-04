import { describe, expect, it } from 'vitest'
import { aDia, cuadreInicial, cuadreQuery, deDia, detalleInicial, detalleQuery, errorDetalle, importe } from '../filtro'
import type { OpcionesReporte } from '../types'

const opciones = (alcance = { estacion: null as number | null, empresa: null as number | null }): OpcionesReporte => ({
  hoy: '2026-10-03',
  estaciones: [{ id: 1, nombre: 'Guatemala' }],
  empresas: [{ id: 1, nombre: 'PIONERA' }],
  monedas: [
    { id: 2, sigla: 'USD', nombre: 'Dólar' },
    { id: 1, sigla: 'GTQ', nombre: 'Quetzal' },
  ],
  alcance,
})

describe('filtros de reportes', () => {
  it('arranca en hoy, en GTQ y con la estación del usuario', () => {
    const f = cuadreInicial(opciones({ estacion: 1, empresa: null }))
    expect(aDia(f.fecha!)).toBe('2026-10-03')
    expect(f).toMatchObject({ estacion: 1, empresa: null, moneda: 'GTQ' })
  })

  it('serializa el cuadre y omite lo vacío', () => {
    const f = cuadreInicial(opciones())
    expect(cuadreQuery(f)).toBe('fecha=2026-10-03&moneda=GTQ')
    expect(cuadreQuery({ ...f, estacion: 1, empresa: 2 })).toBe('fecha=2026-10-03&estacion=1&empresa=2&moneda=GTQ')
    expect(cuadreQuery({ ...f, fecha: null })).toBeNull()
    expect(cuadreQuery({ ...f, moneda: null })).toBeNull()
  })

  it('el detalle usa un solo día cuando falta el segundo y manda solo los filtros activos', () => {
    const f = { ...detalleInicial(opciones()), rango: [deDia('2026-10-01'), null] }
    expect(detalleQuery(f)).toBe('desde=2026-10-01&hasta=2026-10-01')
    expect(detalleQuery({ ...f, rango: [deDia('2026-10-01'), deDia('2026-10-03')], autorizacion: ' 0123 ', soloReferencias: true })).toBe(
      'desde=2026-10-01&hasta=2026-10-03&autorizacion=0123&soloReferencias=1',
    )
    expect(detalleQuery({ ...f, rango: null })).toBeNull()
  })

  it('rechaza rangos de más de 62 días (ambos extremos incluidos) (aun cruzando el cambio de hora)', () => {
    const base = detalleInicial(opciones())
    expect(errorDetalle({ ...base, rango: [deDia('2026-08-01'), deDia('2026-10-01')] })).toBeNull()
    expect(errorDetalle({ ...base, rango: [deDia('2026-08-01'), deDia('2026-10-02')] })).toMatch(/62 días/)
    expect(errorDetalle({ ...base, rango: [deDia('2026-01-01'), deDia('2026-12-31')] })).toMatch(/62 días/)
  })

  it('da formato a los importes', () => {
    expect(importe(2049500, 'GTQ')).toBe('GTQ 20,495.00')
    expect(importe(50, 'USD')).toBe('USD 0.50')
  })
})
